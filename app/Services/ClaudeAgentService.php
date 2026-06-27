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

        $systemBlocks = [
            [
                'type'          => 'text',
                'text'          => $this->buildStaticPrompt($tenant),
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];

        if ($agendamentoPendente) {
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

        $tools    = $this->buildTools();
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
                    'anthropic-beta'    => 'prompt-caching-2024-07-31',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->model,
                    'max_tokens' => 1024,
                    'system'     => $systemBlocks,
                    'tools'      => $tools,
                    'messages'   => $messages,
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
        return match ($name) {
            'buscar_slots'           => $this->toolBuscarSlots($input),
            'criar_agendamento'      => $this->toolCriarAgendamento($input),
            'confirmar_agendamento'  => $this->toolConfirmarAgendamento($agendamentoPendente),
            'cancelar_agendamento'   => $this->toolCancelarAgendamento($agendamentoPendente),
            'transferir_para_humano' => $this->toolTransferirParaHumano(),
            default                  => ['erro' => "Ferramenta desconhecida: {$name}"],
        };
    }

    private function toolBuscarSlots(array $input): array
    {
        $dias  = min((int) ($input['dias'] ?? 4), 7);
        $slots = $this->agendamentoService->buscarHorariosDisponiveis($this->currentTenant, $dias);

        if (empty($slots)) {
            return ['disponivel' => false, 'mensagem' => 'Nenhum horário disponível nos próximos dias.'];
        }

        $linhas = [];
        foreach ($slots as $profissionalId => $diasSlots) {
            foreach ($diasSlots as $data => $horarios) {
                if (! empty($horarios)) {
                    $dataFormatada = \Carbon\Carbon::parse($data)->format('d/m');
                    $linhas[] = "#{$profissionalId}|{$dataFormatada}: " . implode(' ', $horarios);
                }
            }
        }

        return ['slots' => implode("\n", $linhas) ?: 'Nenhum horário disponível.'];
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

    private function toolTransferirParaHumano(): array
    {
        $this->transferir = true;
        return ['sucesso' => true];
    }

    private function buildTools(): array
    {
        return [
            [
                'name'         => 'buscar_slots',
                'description'  => 'Busca horários disponíveis para agendamento nos próximos dias.',
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
                'name'         => 'transferir_para_humano',
                'description'  => 'Transfere a conversa para um atendente humano. Use quando não entender o cliente após 2 tentativas, quando pedir humano ou ficar irritado.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ];
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

        return <<<PROMPT
Você é {$tenant->nome_agente} de {$tenant->nome} ({$tenant->ramo_negocio}). {$tenant->descricao_negocio}
Local:{$tenant->endereco},{$tenant->cidade}|Horários:{$horarios}|Tom:{$tomInstrucao}
PROF:
{$profissionais}
SVC:
{$servicos}{$opcoesPart}{$instrucoes}
REGRAS: mensagens curtas; não invente horários (use buscar_slots); mídia→peça texto; 2x sem entender/irritado→transfira; datas sempre futuras.
CRÍTICO: NUNCA diga que um agendamento foi criado/confirmado sem antes ter chamado criar_agendamento com sucesso — o sistema só registra via ferramenta, não por mensagem de texto.
PROMPT;
    }

    private function formatarHorarios(mixed $horarios): string
    {
        if (empty($horarios)) return 'Consultar pelo WhatsApp';
        if (is_string($horarios)) return $horarios;
        return collect($horarios)->map(fn ($h, $k) => "{$k}: {$h}")->join(' | ');
    }
}
