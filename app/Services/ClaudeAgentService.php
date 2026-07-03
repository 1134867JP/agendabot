<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAgentService
{
    private string $apiKey;
    private string $model;
    private Tenant $currentTenant;
    private array $currentCliente;
    private bool $transferir = false;

    public function __construct(private AgendamentoService $agendamentoService)
    {
        $this->apiKey = (string) config('services.claude.key');
        $this->model  = (string) config('services.claude.model');
    }

    public function processar(
        Tenant $tenant,
        array $mensagens,
        array $clienteInfo,
        ?Agendamento $agendamentoPendente = null
    ): array {
        $this->currentTenant  = $tenant;
        $this->currentCliente = $clienteInfo;
        $this->transferir     = false;

        $hoje = \Carbon\Carbon::now('America/Sao_Paulo');

        $triagem = $tenant->modo_bot === 'triagem';

        $systemBlocks = [
            [
                'type'          => 'text',
                'text'          => $triagem ? $this->buildTriagemPrompt($tenant) : $this->buildStaticPrompt($tenant),
                'cache_control' => ['type' => 'ephemeral'],
            ],
            [
                'type' => 'text',
                'text' => 'HOJE: ' . $hoje->translatedFormat('l, d/m/Y') . ' (' . $hoje->format('Y-m-d') . ').'
                    . ($triagem ? '' : ' Só agende datas futuras a partir de amanhã.'),
            ],
        ];

        if ($triagem) {
            $systemBlocks[] = ['type' => 'text', 'text' => $this->buildAtendimentoBlock($tenant, $hoje)];
        }

        if ($agendamentoPendente && ! $triagem) {
            $dataHora = \Carbon\Carbon::parse($agendamentoPendente->data_hora ?? $agendamentoPendente->inicio)
                ->locale('pt_BR')
                ->translatedFormat('d/m/Y \\às H:i');
            $servico = $agendamentoPendente->profissional?->nome
                ?? $agendamentoPendente->recurso?->nome
                ?? 'serviço';
            $systemBlocks[] = [
                'type' => 'text',
                'text' => "PENDENTE: Agendamento ID #{$agendamentoPendente->id} — {$dataHora} — {$servico}. Use confirmar_agendamento ou cancelar_agendamento se o cliente confirmar/cancelar.",
            ];
        }

        $tools    = $triagem ? $this->buildToolsTriagem() : $this->buildTools();
        $messages = $mensagens;
        $totalUsage = [
            'input_tokens'                => 0,
            'output_tokens'               => 0,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens'     => 0,
        ];
        $resposta = '';

        for ($i = 0; $i < 6; $i++) {
            $response = Http::timeout(30)
                ->retry(2, 1000, fn ($e) => $e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 529)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'         => $this->model,
                    'max_tokens'    => 1024,
                    'cache_control' => ['type' => 'ephemeral'],
                    'system'        => $systemBlocks,
                    'tools'         => $tools,
                    'messages'      => $messages,
                ]);

            if (! $response->successful()) {
                Log::channel('jobs')->error('ClaudeAgentService error', [
                    'status'         => $response->status(),
                    'body'           => $response->body(),
                    'error_type'     => $response->json('error.type'),
                    'error_message'  => $response->json('error.message'),
                    'model'          => $this->model,
                    'total_messages' => count($messages),
                    'first_role'     => $messages[0]['role'] ?? null,
                    'last_role'      => ! empty($messages) ? end($messages)['role'] : null,
                    'roles_sequence' => array_map(fn ($m) => $m['role'] ?? '?', $messages),
                ]);
                return ['resposta' => 'Desculpe, tive um problema técnico. Tente novamente em instantes.', 'transferir' => false, 'usage' => $totalUsage];
            }

            $usage = $response->json('usage', []);
            foreach (['input_tokens', 'output_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens'] as $key) {
                $totalUsage[$key] += (int) ($usage[$key] ?? 0);
            }

            $stopReason = $response->json('stop_reason');
            $content    = $response->json('content', []);

            foreach ($content as $block) {
                if ($block['type'] === 'text') {
                    $resposta = $block['text'];
                }
            }

            if ($stopReason !== 'tool_use') {
                break;
            }

            $assistantMessage = ['role' => 'assistant', 'content' => $content];
            $toolResults = [];

            foreach ($content as $block) {
                if ($block['type'] !== 'tool_use') continue;

                $toolResult = $this->executeTool($block['name'], $block['input'] ?? [], $agendamentoPendente);
                $toolResults[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content'     => json_encode($toolResult),
                ];
            }

            $messages[] = $assistantMessage;
            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        Log::channel('jobs')->debug('ClaudeAgentService usage', [
            'cache_creation' => $totalUsage['cache_creation_input_tokens'],
            'cache_read'     => $totalUsage['cache_read_input_tokens'],
            'input'          => $totalUsage['input_tokens'],
            'output'         => $totalUsage['output_tokens'],
        ]);

        return [
            'resposta'   => $resposta ?: 'Desculpe, não consegui processar sua mensagem. Tente novamente.',
            'transferir' => $this->transferir,
            'usage'      => $totalUsage,
        ];
    }

    private function executeTool(string $name, array $input, ?Agendamento $agendamentoPendente): array
    {
        Log::channel('jobs')->info('TOOL_CALL', [
            'tenant'  => $this->currentTenant->id,
            'tool'    => $name,
            'input'   => $input,
        ]);

        $result = match ($name) {
            'buscar_slots'                 => $this->toolBuscarSlots($input),
            'criar_agendamento'            => $this->toolCriarAgendamento($input),
            'confirmar_agendamento'        => $this->toolConfirmarAgendamento($agendamentoPendente),
            'cancelar_agendamento'         => $this->toolCancelarAgendamento($agendamentoPendente),
            'listar_agendamentos_cliente'  => $this->toolListarAgendamentosCliente(),
            'reagendar_agendamento'        => $this->toolReagendarAgendamento($input),
            'transferir_para_humano'       => $this->toolTransferirParaHumano($input),
            default                        => ['erro' => "Ferramenta desconhecida: {$name}"],
        };

        Log::channel('jobs')->info('TOOL_RESULT', [
            'tenant' => $this->currentTenant->id,
            'tool'   => $name,
            'result' => $result,
        ]);

        return $result;
    }

    private function toolBuscarSlots(array $input): array
    {
        $dias  = min((int) ($input['dias'] ?? 4), 7);
        $slots = $this->agendamentoService->buscarHorariosDisponiveis($this->currentTenant, $dias);

        $hoje = \Carbon\Carbon::now('America/Sao_Paulo')->format('Y-m-d');

        if (empty($slots)) {
            return ['hoje' => $hoje, 'disponivel' => false, 'mensagem' => 'Nenhum horário disponível nos próximos dias.'];
        }

        $linhas = [];
        foreach ($slots as $profissionalId => $diasSlots) {
            foreach ($diasSlots as $data => $horarios) {
                if (! empty($horarios)) {
                    $dataFormatada = \Carbon\Carbon::parse($data)->format('d/m (D)');
                    $linhas[] = "#{$profissionalId}|{$data}|{$dataFormatada}: " . implode(' ', $horarios);
                }
            }
        }

        return ['hoje' => $hoje, 'slots' => implode("\n", $linhas) ?: 'Nenhum horário disponível.'];
    }

    private function toolCriarAgendamento(array $input): array
    {
        try {
            $this->agendamentoService->criarAgendamentoV2($this->currentTenant, array_merge($input, [
                'cliente_id'       => $this->currentCliente['id'],
                'cliente_nome'     => $this->currentCliente['nome'],
                'cliente_telefone' => $this->currentCliente['telefone'],
                'origem'           => 'bot',
            ]));
            return ['sucesso' => true];
        } catch (\App\Exceptions\HorarioIndisponivelException $e) {
            return ['sucesso' => false, 'erro' => 'horario_indisponivel', 'mensagem' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::channel('jobs')->error('toolCriarAgendamento error', ['error' => $e->getMessage(), 'input' => $input]);
            return ['sucesso' => false, 'erro' => 'erro_tecnico'];
        }
    }

    private function toolConfirmarAgendamento(?Agendamento $agendamento): array
    {
        if (! $agendamento) {
            return ['sucesso' => false, 'mensagem' => 'Nenhum agendamento pendente encontrado.'];
        }
        $agendamento->update(['status' => 'confirmado']);
        return ['sucesso' => true];
    }

    private function toolCancelarAgendamento(?Agendamento $agendamento): array
    {
        if (! $agendamento) {
            return ['sucesso' => false, 'mensagem' => 'Nenhum agendamento pendente encontrado.'];
        }
        $this->agendamentoService->cancelar($agendamento);
        return ['sucesso' => true];
    }

    private function toolListarAgendamentosCliente(): array
    {
        $agendamentos = \App\Models\Agendamento::where('cliente_id', $this->currentCliente['id'])
            ->whereNotIn('status', ['cancelado', 'concluido'])
            ->where('data_hora', '>', now())
            ->orderBy('data_hora')
            ->with(['profissional:id,nome', 'servico:id,nome'])
            ->limit(5)
            ->get();

        if ($agendamentos->isEmpty()) {
            return ['agendamentos' => [], 'mensagem' => 'Nenhum agendamento futuro encontrado.'];
        }

        $lista = $agendamentos->map(function ($a) {
            $dataHora = \Carbon\Carbon::parse($a->data_hora)
                ->locale('pt_BR')
                ->translatedFormat('D d/m \\às H:i');
            $profissional = $a->profissional?->nome ?? '—';
            $servico      = $a->servico?->nome ?? '—';
            return "ID #{$a->id} — {$dataHora} — {$profissional} ({$servico})";
        })->join("\n");

        return ['agendamentos' => $lista];
    }

    private function toolReagendarAgendamento(array $input): array
    {
        $agendamentoId = (int) ($input['agendamento_id'] ?? 0);

        $agendamento = \App\Models\Agendamento::where('id', $agendamentoId)
            ->where('cliente_id', $this->currentCliente['id'])
            ->whereNotIn('status', ['cancelado', 'concluido'])
            ->first();

        if (! $agendamento) {
            return ['sucesso' => false, 'erro' => 'agendamento_invalido', 'mensagem' => 'Agendamento não encontrado ou não pertence a este cliente.'];
        }

        try {
            $this->agendamentoService->reagendar($agendamento, $input);
            return ['sucesso' => true];
        } catch (\App\Exceptions\HorarioIndisponivelException $e) {
            return ['sucesso' => false, 'erro' => 'horario_indisponivel', 'mensagem' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::channel('jobs')->error('toolReagendarAgendamento error', ['error' => $e->getMessage(), 'input' => $input]);
            return ['sucesso' => false, 'erro' => 'erro_tecnico'];
        }
    }

    private function toolTransferirParaHumano(array $input = []): array
    {
        $this->transferir = true;

        // Em modo triagem, o resumo estruturado da conversa fica registrado no log
        // para a atendente ter o handoff — os dados também permanecem no histórico.
        if (! empty($input['resumo'])) {
            Log::channel('jobs')->info('TRIAGEM_HANDOFF', [
                'tenant'      => $this->currentTenant->id,
                'cliente'     => $this->currentCliente['telefone'] ?? null,
                'resumo'      => $input['resumo'],
                'nome'        => $input['nome_cliente'] ?? null,
                'preferencia' => $input['preferencia'] ?? null,
            ]);
        }

        return ['sucesso' => true];
    }

    private function buildTools(): array
    {
        return [
            [
                'name'         => 'buscar_slots',
                'description'  => 'Busca horários disponíveis para agendamento nos próximos dias. DEVE ser chamada antes de criar_agendamento — nunca ofereça nem aceite horários sem antes chamar esta ferramenta.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'dias' => ['type' => 'integer', 'description' => 'Número de dias para buscar (padrão 4, máximo 7)'],
                    ],
                ],
            ],
            [
                'name'         => 'criar_agendamento',
                'description'  => 'Registra o agendamento no sistema. OBRIGATÓRIO chamar esta ferramenta assim que tiver: nome do cliente, profissional_id, servico_id, data e horário — não envie mensagem de confirmação sem antes chamar esta ferramenta com sucesso. O agendamento só existe no sistema após a chamada.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'cliente_nome'    => ['type' => 'string',           'description' => 'Nome do cliente'],
                        'profissional_id' => ['type' => 'integer',          'description' => 'ID do profissional'],
                        'servico_id'      => ['type' => 'integer',          'description' => 'ID do serviço'],
                        'data'            => ['type' => 'string',           'description' => 'Data no formato YYYY-MM-DD'],
                        'horario'         => ['type' => 'string',           'description' => 'Horário no formato HH:MM'],
                        'opcao_extra'     => ['type' => ['string', 'null'], 'description' => 'Opção extra (opcional)'],
                        'observacoes'     => ['type' => ['string', 'null'], 'description' => 'Observações (opcional)'],
                    ],
                    'required' => ['cliente_nome', 'profissional_id', 'servico_id', 'data', 'horario'],
                ],
            ],
            [
                'name'         => 'confirmar_agendamento',
                'description'  => 'Confirma o agendamento pendente existente quando o cliente expressa confirmação.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'         => 'cancelar_agendamento',
                'description'  => 'Cancela o agendamento pendente existente quando o cliente solicita cancelamento.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'         => 'listar_agendamentos_cliente',
                'description'  => 'Lista os próximos agendamentos do cliente atual. DEVE ser chamada antes de reagendar_agendamento para identificar qual agendamento o cliente quer alterar.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'         => 'reagendar_agendamento',
                'description'  => 'Atualiza um agendamento existente com nova data, hora e opcionalmente novo profissional ou serviço. OBRIGATÓRIO chamar listar_agendamentos_cliente antes para obter o agendamento_id correto. Use buscar_slots para confirmar disponibilidade antes de chamar esta ferramenta.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => ['agendamento_id', 'data', 'hora'],
                    'properties' => [
                        'agendamento_id'  => ['type' => 'integer', 'description' => 'ID do agendamento a alterar (obtido via listar_agendamentos_cliente)'],
                        'data'            => ['type' => 'string',  'description' => 'Nova data no formato YYYY-MM-DD'],
                        'hora'            => ['type' => 'string',  'description' => 'Novo horário no formato HH:MM'],
                        'profissional_id' => ['type' => 'integer', 'description' => 'Novo profissional (opcional — mantém o atual se omitido)'],
                        'servico_id'      => ['type' => 'integer', 'description' => 'Novo serviço (opcional — mantém o atual se omitido)'],
                    ],
                ],
            ],
            [
                'name'          => 'transferir_para_humano',
                'description'   => 'Transfere a conversa para um atendente humano. Use quando não entender o cliente após 2 tentativas, quando pedir humano ou ficar irritado.',
                'input_schema'  => ['type' => 'object', 'properties' => new \stdClass()],
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];
    }

    private function buildToolsTriagem(): array
    {
        return [
            [
                'name'          => 'transferir_para_humano',
                'description'   => 'Encaminha a conversa para uma atendente humana finalizar o agendamento. Chame assim que tiver: nome do cliente, serviço/motivo desejado e a preferência de dia/horário. NUNCA confirme horário ou agendamento antes — quem confere a agenda é a atendente.',
                'input_schema'  => [
                    'type'       => 'object',
                    'properties' => [
                        'resumo'       => ['type' => 'string', 'description' => 'Resumo curto do que o cliente deseja (serviço/motivo + preferência de dia e horário)'],
                        'nome_cliente' => ['type' => 'string', 'description' => 'Nome do cliente'],
                        'preferencia'  => ['type' => 'string', 'description' => 'Preferência de dia/horário informada pelo cliente'],
                    ],
                ],
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];
    }

    /**
     * Bloco dinâmico (não cacheado) informando ao bot se está ou não dentro do
     * horário de atendimento da atendente e como ajustar a expectativa do cliente.
     */
    private function buildAtendimentoBlock(Tenant $tenant, \Carbon\Carbon $agora): string
    {
        $texto = $tenant->horarioAtendimentoTexto();

        if ($texto === '') {
            return 'ATENDIMENTO: Após transferir, informe que uma atendente dará sequência em instantes.';
        }

        if ($tenant->emHorarioAtendimento($agora)) {
            return "ATENDIMENTO: Estamos DENTRO do horário de atendimento ({$texto}). Após transferir, diga que uma atendente vai dar sequência em instantes.";
        }

        $mensagem = trim((string) $tenant->mensagem_fora_horario);
        $instrucao = $mensagem !== ''
            ? "Use esta mensagem ao encerrar: \"{$mensagem}\""
            : "Avise, de forma simpática, que uma atendente retornará no horário de atendimento ({$texto}).";

        return "ATENDIMENTO: Estamos FORA do horário de atendimento ({$texto}). Colete normalmente os dados e transfira, mas {$instrucao}";
    }

    private function buildTriagemPrompt(Tenant $tenant): string
    {
        $profissionais = $tenant->profissionais()->where('ativo', true)->with('servicos:id,nome')->get()
            ->map(function ($p) {
                $servNomes = $p->servicos->pluck('nome')->join(', ');
                $base = "- {$p->nome}";
                return $servNomes ? "{$base} → {$servNomes}" : $base;
            })
            ->join("\n");

        $servicos = $tenant->servicos()->where('ativo', true)->get()
            ->map(fn ($s) => "- {$s->nome}" .
                ($s->valor_min ? " (R$ {$s->valor_min}" . ($s->valor_max ? "-{$s->valor_max}" : '') . ")" : '') .
                ($s->requer_avaliacao ? ' [requer avaliação]' : ''))
            ->join("\n");

        $tomInstrucao = match ($tenant->tom_voz) {
            'formal'       => 'Linguagem profissional e respeitosa. Sem emojis. Use "Senhor/Senhora".',
            'descontraido' => 'Linguagem leve e simpática. Emojis liberados. Pode usar gírias suaves.',
            default        => 'Linguagem clara e amigável. Emojis com moderação. Tratamento informal mas respeitoso.',
        };

        $instrucoes  = $tenant->instrucoes_extras ? "\nINSTRUÇÕES ESPECÍFICAS DO NEGÓCIO:\n{$tenant->instrucoes_extras}" : '';
        $servicosPart = $servicos ? "\nSERVIÇOS OFERECIDOS (apenas para contexto):\n{$servicos}\n" : '';
        $profPart     = $profissionais ? "\nPROFISSIONAIS (apenas para contexto):\n{$profissionais}\n" : '';

        return <<<PROMPT
Você é {$tenant->nome_agente} de {$tenant->nome} ({$tenant->ramo_negocio}). {$tenant->descricao_negocio}
Local: {$tenant->endereco}, {$tenant->cidade} | Tom: {$tomInstrucao}

SEU PAPEL: Fazer o PRÉ-ATENDIMENTO. Você NÃO agenda e NÃO tem acesso à agenda — a disponibilidade é conferida por uma atendente humana. Seu trabalho é acolher o cliente, entender o que ele precisa e coletar os dados para a atendente concluir.
{$servicosPart}{$profPart}{$instrucoes}

FLUXO OBRIGATÓRIO:
1. Saúde o cliente e pergunte o que ele deseja (qual serviço ou motivo).
2. Colete o NOME do cliente.
3. Colete a PREFERÊNCIA de dia e horário (ex: "quarta de manhã", "sábado à tarde").
4. Quando tiver nome + serviço/motivo + preferência, chame a ferramenta transferir_para_humano passando resumo, nome_cliente e preferencia.
5. Depois de transferir, encerre conforme a orientação de ATENDIMENTO (dentro/fora do horário).

REGRAS CRÍTICAS:
- JAMAIS diga que um horário está disponível, reservado ou confirmado. Você não vê a agenda.
- JAMAIS prometa data/hora específica — apenas registre a PREFERÊNCIA do cliente.
- Deixe claro, quando fizer sentido, que uma atendente vai confirmar a disponibilidade.
- Mensagens curtas (máx 3 linhas). Se não entender após 2 tentativas ou o cliente pedir humano, chame transferir_para_humano.
- Mídia recebida → peça para descrever em texto.
PROMPT;
    }

    public function buildStaticPrompt(Tenant $tenant): string
    {
        $profissionais = $tenant->profissionais()->where('ativo', true)->with('servicos:id,nome')->get()
            ->map(function ($p) {
                $servNomes = $p->servicos->pluck('nome')->join(', ');
                $base = "- ID {$p->id}: {$p->nome}";
                return $servNomes ? "{$base} → {$servNomes}" : $base;
            })
            ->join("\n");

        $servicos = $tenant->servicos()->where('ativo', true)->get()
            ->map(fn ($s) => "- ID {$s->id}: {$s->nome}" .
                ($s->valor_min ? " (R$ {$s->valor_min}" . ($s->valor_max ? "-{$s->valor_max}" : '') . ")" : '') .
                " — {$s->duracao_minutos}min" .
                ($s->requer_avaliacao ? ' [requer avaliação]' : ''))
            ->join("\n");

        $opcoes = $tenant->opcoes_extras()->where('ativo', true)->get()
            ->groupBy('tipo')
            ->map(fn ($grupo, $tipo) => strtoupper($tipo) . ': ' . $grupo->pluck('nome')->join(', '))
            ->join("\n");

        $horarios = $this->formatarHorarios($tenant->horarios_funcionamento);

        $tomInstrucao = match ($tenant->tom_voz) {
            'formal'       => 'Linguagem profissional e respeitosa. Sem emojis. Use "Senhor/Senhora".',
            'descontraido' => 'Linguagem leve e simpática. Emojis liberados. Pode usar gírias suaves.',
            default        => 'Linguagem clara e amigável. Emojis com moderação. Tratamento informal mas respeitoso.',
        };

        $instrucoes = $tenant->instrucoes_extras ? "\nINSTRUÇÕES ESPECÍFICAS DO NEGÓCIO:\n{$tenant->instrucoes_extras}" : '';
        $opcoesPart = $opcoes ? "\n{$opcoes}\n" : '';
        $maxTentativas = $tenant->triagemConfig()['max_tentativas_sem_entender'];

        return <<<PROMPT
Você é {$tenant->nome_agente} de {$tenant->nome} ({$tenant->ramo_negocio}). {$tenant->descricao_negocio}
Local: {$tenant->endereco}, {$tenant->cidade} | Horários: {$horarios} | Tom: {$tomInstrucao}

PROFISSIONAIS:
{$profissionais}

SERVIÇOS:
{$servicos}{$opcoesPart}{$instrucoes}

FLUXO OBRIGATÓRIO PARA REAGENDAMENTO:
1. Chame listar_agendamentos_cliente → mostre a lista ao cliente
2. Cliente escolhe qual quer alterar → chame buscar_slots para mostrar novos horários
3. Cliente confirma novo horário → chame reagendar_agendamento com o agendamento_id e os novos dados
4. reagendar_agendamento sucesso=true → confirme o novo horário ao cliente
- Se retornar horario_indisponivel: chame buscar_slots novamente e ofereça alternativas

FLUXO OBRIGATÓRIO PARA NOVO AGENDAMENTO:
1. Chame buscar_slots → apresente as opções reais retornadas
2. Cliente escolhe → confirme em texto: "Ok! [serviço] com [profissional] em [data] às [hora], certo?"
3. Cliente confirma → chame criar_agendamento com os dados EXATOS do slot escolhido
4. criar_agendamento retorna sucesso=true → informe o cliente que está agendado

REGRAS:
- Mensagens curtas (máx 3 linhas)
- JAMAIS invente horários: use SOMENTE as horas retornadas por buscar_slots
- Se criar_agendamento retornar horario_indisponivel: chame buscar_slots novamente e ofereça alternativas
- Mídia recebida → peça para descrever em texto
- {$maxTentativas} mensagens sem entender / cliente irritado → chame transferir_para_humano
- Datas sempre futuras (não agende para hoje ou passado)

CRÍTICO: NUNCA diga que um agendamento foi criado/confirmado sem antes ter chamado criar_agendamento com sucesso. O sistema só registra via ferramenta — mensagem de texto não cria agendamento.
PROMPT;
    }

    private function formatarHorarios(mixed $horarios): string
    {
        if (empty($horarios)) return 'Consultar pelo WhatsApp';
        if (is_string($horarios)) return $horarios;
        return collect($horarios)->map(fn ($h, $k) => "{$k}: {$h}")->join(' | ');
    }
}
