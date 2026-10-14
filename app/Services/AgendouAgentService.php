<?php

namespace App\Services;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\Agendamento;
use App\Models\Tenant;
use App\Services\AI\AiOrchestrator;
use App\Services\AI\Exceptions\AiLimitExceededException;
use App\Services\AI\Exceptions\AiProviderException;
use App\Support\WhatsappTexto;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AgendouAgentService
{
    private const NOMES_GENERICOS = [
        'cliente', 'cliente whatsapp', 'cliente simulado', 'você', 'voce', 'you',
        'nome', 'nome do cliente', 'não informado', 'nao informado', 'desconhecido', 'sem nome',
        'paciente', 'nome do paciente', 'usuário', 'usuario',
    ];

    private Tenant $currentTenant;

    private array $currentCliente;

    private bool $transferir = false;

    /** Confirmação textual só é permitida após a reserva gravada neste turno. */
    private bool $agendamentoCriadoNesteTurno = false;

    private ?int $agendamentoCriadoId = null;

    /**
     * Última alteração gravada neste turno ('criado', 'reagendado', 'cancelado',
     * 'confirmado') e o agendamento afetado. Permite responder com os dados
     * reais quando o texto do modelo vem degenerado.
     *
     * @var array{tipo: string, agendamento: Agendamento}|null
     */
    private ?array $acaoConcluida = null;

    /** Mensagens do cliente no histórico deste turno (para a trava da transferência na triagem). */
    private array $mensagensDoCliente = [];

    private bool $transferenciaRecusada = false;

    /** A partir daqui a conversa é considerada travada e a transferência é liberada sem os dados. */
    private const MENSAGENS_PARA_LIBERAR_TRANSFERENCIA = 4;

    private const PEDIDO_DE_HUMANO = '/\b(atendente|humano|humana|pessoa|algu[eé]m|rob[oô]|recep[cç][aã]o|secret[aá]ria)\b/iu';

    public function __construct(
        private AgendamentoService $agendamentoService,
        private AiOrchestrator $ai,
    ) {}

    public function processar(
        Tenant $tenant,
        array $mensagens,
        array $clienteInfo,
        ?Agendamento $agendamentoPendente = null
    ): array {
        $this->currentTenant = $tenant;
        $this->currentCliente = $clienteInfo;
        $this->transferir = false;
        $this->agendamentoCriadoNesteTurno = false;
        $this->agendamentoCriadoId = null;
        $this->acaoConcluida = null;
        $this->transferenciaRecusada = false;
        // O histórico chega com mensagens seguidas do cliente unidas por quebra de linha.
        $this->mensagensDoCliente = collect($mensagens)
            ->filter(fn ($m) => ($m['role'] ?? null) === 'user' && is_string($m['content'] ?? null))
            ->flatMap(fn ($m) => array_filter(array_map('trim', explode("\n", $m['content']))))
            ->values()
            ->all();

        $hoje = Carbon::now($tenant->resolvedTimezone());

        $triagem = $tenant->modo_bot === 'triagem';

        $systemBlocks = [
            [
                'type' => 'text',
                'text' => $triagem ? $this->buildTriagemPrompt($tenant) : $this->buildStaticPrompt($tenant, (bool) $agendamentoPendente),
                'cache_control' => ['type' => 'ephemeral'],
            ],
            [
                'type' => 'text',
                'text' => 'HOJE: '.$hoje->translatedFormat('l, d/m/Y').' ('.$hoje->format('Y-m-d').'), agora são '.$hoje->format('H:i').'.'
                    .($triagem ? '' : ' Pode agendar para hoje ou dias seguintes, sempre com horários vindos de buscar_slots.'),
            ],
        ];

        if ($triagem) {
            $systemBlocks[] = ['type' => 'text', 'text' => $this->buildAtendimentoBlock($tenant, $hoje)];
        }

        if ($agendamentoPendente && ! $triagem) {
            $dataHora = Carbon::parse($agendamentoPendente->data_hora ?? $agendamentoPendente->inicio)
                ->locale('pt_BR')
                ->translatedFormat('d/m/Y \\à\\s H:i');
            $servico = collect([
                $agendamentoPendente->descricaoServicos(),
                $agendamentoPendente->profissional?->nome ?? $agendamentoPendente->recurso?->nome,
            ])->filter()->join(' com ') ?: 'serviço';
            $systemBlocks[] = [
                'type' => 'text',
                'text' => "PENDENTE: Agendamento ID #{$agendamentoPendente->id} — {$dataHora} — {$servico}. Já está agendado — NÃO ofereça nem tente criar outro horário para este pedido. Use confirmar_agendamento ou cancelar_agendamento se o cliente confirmar/cancelar. Se o cliente responder 'sim', 'pode confirmar' ou 'confirmo' sobre este agendamento, chame confirmar_agendamento e NÃO chame buscar_slots: o horário dele aparece ocupado justamente por já ser dele. Se o cliente só agradecer (ex: 'vlw', 'obrigado', 'ok'), responda normalmente sem chamar nenhuma ferramenta.",
            ];
        }

        $tools = $triagem ? $this->buildToolsTriagem() : $this->buildTools((bool) $agendamentoPendente);
        $messages = $mensagens;
        $totalUsage = [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ];
        $resposta = '';

        for ($i = 0; $i < 6; $i++) {
            try {
                $response = $this->ai->processar($tenant, [
                    'max_tokens' => 1024,
                    'system' => $systemBlocks,
                    'tools' => $tools,
                    'messages' => $messages,
                ]);
            } catch (AiProviderException|AiLimitExceededException $e) {
                Log::channel('jobs')->error('AI_ORCHESTRATOR_ERROR', [
                    'tenant_id' => $tenant->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'total_messages' => count($messages),
                ]);

                return ['resposta' => 'Tive uma instabilidade e vou encaminhar você para um atendente. 🙋', 'transferir' => true, 'usage' => $totalUsage];
            } catch (\Throwable $e) {
                // Falhas inesperadas (por exemplo, uma resposta malformada de um
                // provider) não podem encerrar o job sem resposta ao cliente. O
                // detalhe fica no log para investigação, enquanto a conversa segue
                // com handoff seguro para a equipe.
                Log::channel('jobs')->error('AI_UNEXPECTED_ERROR', [
                    'tenant_id' => $tenant->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'total_messages' => count($messages),
                ]);

                return ['resposta' => 'Tive uma instabilidade e vou encaminhar você para um atendente. 🙋', 'transferir' => true, 'usage' => $totalUsage];
            }

            $usage = $response->usage;
            foreach (['input_tokens', 'output_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens'] as $key) {
                $totalUsage[$key] += (int) ($usage[$key] ?? 0);
            }

            $stopReason = $response->stopReason;
            $content = $response->content;

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
                if ($block['type'] !== 'tool_use') {
                    continue;
                }

                $toolResult = $this->executeTool($block['name'], $block['input'] ?? [], $agendamentoPendente);
                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => json_encode($toolResult),
                ];

                // A transferência encerra o fluxo do bot. Não delegamos a mensagem
                // final a uma segunda chamada de IA: em um fallback de provider esse
                // histórico de ferramenta pode ser interpretado de forma diferente e
                // gerar trechos soltos, outro idioma ou uma nova transferência.
                if ($this->transferir) {
                    return [
                        'resposta' => $this->mensagemTransferencia($tenant, $hoje),
                        'transferir' => true,
                        'usage' => $totalUsage,
                    ];
                }
            }

            $messages[] = $assistantMessage;
            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        Log::channel('jobs')->debug('ClaudeAgentService usage', [
            'cache_creation' => $totalUsage['cache_creation_input_tokens'],
            'cache_read' => $totalUsage['cache_read_input_tokens'],
            'input' => $totalUsage['input_tokens'],
            'output' => $totalUsage['output_tokens'],
        ]);

        // Alguns modelos (visto no Groq gpt-oss) degeneram em espaços invisíveis no
        // meio da frase. Esse texto não pode chegar ao cliente: se algo foi gravado
        // neste turno, respondemos com os dados reais; senão pedimos para repetir.
        if (WhatsappTexto::pareceDegenerado($resposta)) {
            Log::channel('jobs')->warning('BOT_DEGENERATE_AI_RESPONSE', [
                'tenant_id' => $tenant->id,
                'acao_concluida' => $this->acaoConcluida['tipo'] ?? null,
            ]);
            $resposta = $this->respostaDaAcaoConcluida($tenant) ?? '';
        }
        $resposta = WhatsappTexto::normalizar($resposta);

        // Alguns modelos menores podem responder que transferiram a conversa sem
        // efetivamente chamar a ferramenta. Nunca deixamos esse texto solto: ele vira
        // um handoff real, pausa o bot no job e usa a mensagem padronizada abaixo.
        if ($triagem && ! $this->transferenciaRecusada && $this->respostaIndicaTransferencia($resposta)) {
            return [
                'resposta' => $this->mensagemTransferencia($tenant, $hoje),
                'transferir' => true,
                'usage' => $totalUsage,
            ];
        }

        // Prompt não é uma garantia: em especial em fallback para modelos menores,
        // o modelo pode escrever "está agendado" sem ter chamado a ferramenta. Não
        // deixamos uma promessa dessas chegar ao cliente se a reserva não foi gravada.
        if (! $triagem && ! $agendamentoPendente && ! $this->agendamentoCriadoNesteTurno
            && $this->respostaAfirmaNovoAgendamento($resposta)) {
            Log::channel('jobs')->warning('BOT_BLOCKED_UNPERSISTED_APPOINTMENT_CONFIRMATION', [
                'tenant_id' => $tenant->id,
                'cliente_id' => $clienteInfo['id'] ?? null,
                'resposta' => $resposta,
            ]);

            return [
                'resposta' => 'Não consegui concluir a reserva por aqui. Vou encaminhar você para confirmar o horário.',
                'transferir' => true,
                'usage' => $totalUsage,
            ];
        }

        return [
            'resposta' => $resposta ?: 'Desculpe, não consegui processar sua mensagem. Pode repetir, por favor?',
            'transferir' => $this->transferir,
            'usage' => $totalUsage,
            'agendamento_id' => $this->agendamentoCriadoId,
        ];
    }

    private function executeTool(string $name, array $input, ?Agendamento $agendamentoPendente): array
    {
        Log::channel('jobs')->info('TOOL_CALL', [
            'tenant' => $this->currentTenant->id,
            'tool' => $name,
            'input' => $input,
        ]);

        $result = match ($name) {
            'buscar_slots' => $this->toolBuscarSlots($input),
            'criar_agendamento' => $this->toolCriarAgendamento($input),
            'confirmar_agendamento' => $this->toolConfirmarAgendamento($agendamentoPendente),
            'cancelar_agendamento' => $this->toolCancelarAgendamento($agendamentoPendente),
            'listar_agendamentos_cliente' => $this->toolListarAgendamentosCliente(),
            'reagendar_agendamento' => $this->toolReagendarAgendamento($input),
            'transferir_para_humano' => $this->toolTransferirParaHumano($input),
            default => ['erro' => "Ferramenta desconhecida: {$name}"],
        };

        Log::channel('jobs')->info('TOOL_RESULT', [
            'tenant' => $this->currentTenant->id,
            'tool' => $name,
            'result' => $result,
        ]);

        return $result;
    }

    private function toolBuscarSlots(array $input): array
    {
        $tenant = $this->currentTenant;
        $dias = min((int) ($input['dias'] ?? 4), 7);
        $servicoIds = array_values(array_filter(array_map('intval', (array) ($input['servico_ids'] ?? $input['servico_id'] ?? []))));
        $profissionalId = ! empty($input['profissional_id']) ? (int) $input['profissional_id'] : null;

        try {
            $slots = $this->agendamentoService->buscarHorariosDisponiveis($tenant, $dias, $servicoIds, $profissionalId);
        } catch (\InvalidArgumentException $e) {
            return ['disponivel' => false, 'erro' => 'servico_invalido', 'mensagem' => 'Algum servico_id não existe. Use os IDs da lista SERVIÇOS.'];
        }

        $hoje = Carbon::now($tenant->resolvedTimezone())->format('Y-m-d');

        if (empty(array_filter($slots))) {
            return $this->comAgendamentosDoCliente(['hoje' => $hoje, 'disponivel' => false, 'mensagem' => $servicoIds
                ? 'Nenhum profissional que faz esse(s) serviço(s) tem horário livre nos próximos dias.'
                : 'Nenhum horário disponível nos próximos dias.']);
        }

        $nomes = $tenant->profissionais()->whereIn('id', array_keys($slots))->pluck('nome', 'id');
        $catalogo = app(CatalogoServicos::class);
        $linhas = [];
        foreach ($slots as $id => $diasSlots) {
            $resumo = '';
            if ($servicoIds) {
                $montagem = $catalogo->montar($tenant, $servicoIds, $tenant->profissionais()->find($id));
                $valor = $montagem['valor_total'] !== null ? ' · R$ '.number_format($montagem['valor_total'], 2, ',', '.') : '';
                $resumo = " ({$montagem['nomes']}: {$montagem['duracao_minutos']}min{$valor})";
            }
            foreach ($diasSlots as $data => $horarios) {
                if (! empty($horarios)) {
                    $dataFormatada = Carbon::parse($data)->locale('pt_BR')->translatedFormat('d/m (D)');
                    $linhas[] = "#{$id} {$nomes[$id]}{$resumo}|{$data}|{$dataFormatada}: ".implode(' ', $horarios);
                }
            }
        }

        return $this->comAgendamentosDoCliente(['hoje' => $hoje, 'slots' => implode("\n", $linhas)]);
    }

    /**
     * Os horários do próprio cliente saem da lista de livres. Sem este aviso o modelo
     * respondia "15:00 indisponível" quando o cliente só confirmava o que já tinha marcado.
     */
    private function comAgendamentosDoCliente(array $resultado): array
    {
        $agendamentos = $this->agendamentosFuturosDoCliente();
        if ($agendamentos !== null) {
            $resultado['agendamentos_do_cliente'] = $agendamentos."\n"
                .'Estes horários já são do cliente e por isso não aparecem como livres. Não diga que estão indisponíveis; '
                .'se ele quer manter, use confirmar_agendamento.';
        }

        return $resultado;
    }

    private function toolCriarAgendamento(array $input): array
    {
        // O bot pergunta o nome ao cliente; o cadastro costuma ter só o nome do
        // perfil do WhatsApp, que fica como reserva se a IA não informar um nome real.
        $nomeInformado = $this->nomeRealInformado($input['cliente_nome'] ?? null);

        try {
            $agendamento = $this->agendamentoService->criarAgendamentoV2($this->currentTenant, array_merge($input, [
                'cliente_id' => $this->currentCliente['id'],
                'cliente_nome' => $nomeInformado ?? $this->currentCliente['nome'],
                'cliente_telefone' => $this->currentCliente['telefone'],
                'origem' => 'bot',
            ]));

            $this->agendamentoCriadoNesteTurno = true;
            $this->agendamentoCriadoId = $agendamento->id;
            $this->acaoConcluida = ['tipo' => 'criado', 'agendamento' => $agendamento];

            return ['sucesso' => true, 'agendamento_id' => $agendamento->id];
        } catch (HorarioIndisponivelException $e) {
            return ['sucesso' => false, 'erro' => 'horario_indisponivel', 'mensagem' => $e->getMessage()];
        } catch (\InvalidArgumentException $e) {
            // Dado em formato errado: o modelo recebe o formato esperado e tenta de novo.
            return ['sucesso' => false, 'erro' => 'dados_invalidos', 'mensagem' => $e->getMessage()];
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
        $agendamento->update(['status' => 'confirmado', 'confirmed_by_client_at' => now()]);
        $this->acaoConcluida = ['tipo' => 'confirmado', 'agendamento' => $agendamento];

        return ['sucesso' => true];
    }

    private function toolCancelarAgendamento(?Agendamento $agendamento): array
    {
        if (! $agendamento) {
            return ['sucesso' => false, 'mensagem' => 'Nenhum agendamento pendente encontrado.'];
        }
        $this->agendamentoService->cancelar($agendamento);
        $this->acaoConcluida = ['tipo' => 'cancelado', 'agendamento' => $agendamento];

        return ['sucesso' => true];
    }

    private function toolListarAgendamentosCliente(): array
    {
        $lista = $this->agendamentosFuturosDoCliente();

        if ($lista === null) {
            return ['agendamentos' => [], 'mensagem' => 'Nenhum agendamento futuro encontrado.'];
        }

        return ['agendamentos' => $lista];
    }

    /** Próximos agendamentos ativos do cliente atual, uma linha por agendamento, ou null. */
    private function agendamentosFuturosDoCliente(): ?string
    {
        $agendamentos = Agendamento::where('cliente_id', $this->currentCliente['id'])
            ->whereNotIn('status', ['cancelado', 'concluido'])
            ->where('data_hora', '>', now())
            ->orderBy('data_hora')
            ->with(['profissional:id,nome', 'servico:id,nome', 'itensServico'])
            ->limit(5)
            ->get();

        if ($agendamentos->isEmpty()) {
            return null;
        }

        return $agendamentos->map(function ($a) {
            $dataHora = Carbon::parse($a->data_hora)
                ->locale('pt_BR')
                ->translatedFormat('D d/m \\à\\s H:i');
            $profissional = $a->profissional?->nome ?? '—';
            $servico = $a->descricaoServicos() ?? '—';

            return "ID #{$a->id} — {$dataHora} — {$profissional} ({$servico})";
        })->join("\n");
    }

    private function toolReagendarAgendamento(array $input): array
    {
        $agendamentoId = (int) ($input['agendamento_id'] ?? 0);

        $agendamento = Agendamento::where('id', $agendamentoId)
            ->where('cliente_id', $this->currentCliente['id'])
            ->whereNotIn('status', ['cancelado', 'concluido'])
            ->first();

        if (! $agendamento) {
            return ['sucesso' => false, 'erro' => 'agendamento_invalido', 'mensagem' => 'Agendamento não encontrado ou não pertence a este cliente.'];
        }

        try {
            $this->agendamentoService->reagendar($agendamento, $input);
            $this->acaoConcluida = ['tipo' => 'reagendado', 'agendamento' => $agendamento->fresh()];

            return ['sucesso' => true];
        } catch (HorarioIndisponivelException $e) {
            return ['sucesso' => false, 'erro' => 'horario_indisponivel', 'mensagem' => $e->getMessage()];
        } catch (\InvalidArgumentException $e) {
            // Dado em formato errado: o modelo recebe o formato esperado e tenta de novo.
            return ['sucesso' => false, 'erro' => 'dados_invalidos', 'mensagem' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::channel('jobs')->error('toolReagendarAgendamento error', ['error' => $e->getMessage(), 'input' => $input]);

            return ['sucesso' => false, 'erro' => 'erro_tecnico'];
        }
    }

    private function toolTransferirParaHumano(array $input = []): array
    {
        if ($this->currentTenant->modo_bot === 'triagem' && ($faltando = $this->dadosFaltandoParaTransferir($input))) {
            $this->transferenciaRecusada = true;
            Log::channel('jobs')->info('TRIAGEM_HANDOFF_RECUSADO', [
                'tenant' => $this->currentTenant->id,
                'faltando' => $faltando,
            ]);

            return [
                'sucesso' => false,
                'erro' => 'dados_incompletos',
                'mensagem' => 'Ainda faltam: '.implode(' e ', $faltando).'. Responda a dúvida do cliente usando as '
                    .'informações do negócio e pergunte o que falta. Não diga que transferiu.',
            ];
        }

        $this->transferir = true;

        // Em modo triagem, o resumo estruturado da conversa fica registrado no log
        // para a atendente ter o handoff — os dados também permanecem no histórico.
        if (! empty($input['resumo'])) {
            Log::channel('jobs')->info('TRIAGEM_HANDOFF', [
                'tenant' => $this->currentTenant->id,
                'cliente' => $this->currentCliente['telefone'] ?? null,
                'resumo' => $input['resumo'],
                'nome' => $input['nome_cliente'] ?? null,
                'preferencia' => $input['preferencia'] ?? null,
            ]);
        }

        return ['sucesso' => true];
    }

    /**
     * Na triagem a atendente precisa de nome e preferência para dar sequência. O modelo
     * às vezes transfere logo na primeira dúvida inventando esses dados; só liberamos
     * sem eles quando o cliente pede uma pessoa ou a conversa não avança.
     *
     * @return list<string> dados que faltam (vazio = pode transferir)
     */
    private function dadosFaltandoParaTransferir(array $input): array
    {
        $ultima = (string) end($this->mensagensDoCliente);
        if (preg_match(self::PEDIDO_DE_HUMANO, $ultima) === 1
            || count($this->mensagensDoCliente) >= self::MENSAGENS_PARA_LIBERAR_TRANSFERENCIA) {
            return [];
        }

        return array_values(array_filter([
            $this->nomeRealInformado($input['nome_cliente'] ?? null) === null ? 'o nome do cliente' : null,
            trim((string) ($input['preferencia'] ?? '')) === '' ? 'a preferência de dia/horário' : null,
        ]));
    }

    /**
     * Com um agendamento pendente já em aberto, criar_agendamento fica fora do conjunto
     * de ferramentas: um provider mais fraco (Gemini/Groq) pode ignorar a instrução de
     * texto do bloco PENDENTE e tentar "reservar de novo", batendo contra a própria
     * reserva recém-criada (horario_indisponivel). Remover a ferramenta torna isso
     * estruturalmente impossível — só reagendar/confirmar/cancelar seguem disponíveis.
     * Sem agendamento, o inverso: as ferramentas de gestão saem (não há o que gerir e
     * elas custavam ~40% dos tokens de cada chamada).
     */
    private function buildTools(bool $temAgendamentoPendente = false): array
    {
        $tools = [
            [
                'name' => 'buscar_slots',
                'description' => 'Busca horários disponíveis nos próximos dias. DEVE ser chamada antes de criar_agendamento — nunca ofereça nem aceite horários sem antes chamar esta ferramenta. Informe servico_ids sempre que souber o(s) serviço(s): assim só aparecem profissionais que fazem tudo e horários em que o atendimento inteiro cabe.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'dias' => ['type' => 'integer', 'description' => 'Número de dias para buscar (padrão 4, máximo 7)'],
                        'servico_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'IDs dos serviços pedidos (ex.: corte e barba = dois IDs)'],
                        'profissional_id' => ['type' => 'integer', 'description' => 'Só quando o cliente pedir um profissional específico'],
                    ],
                ],
            ],
            [
                'name' => 'criar_agendamento',
                'description' => 'Registra o agendamento no sistema. OBRIGATÓRIO chamar esta ferramenta assim que tiver: nome do cliente, profissional_id, servico_ids, data e horário — não envie mensagem de confirmação sem antes chamar esta ferramenta com sucesso. O agendamento só existe no sistema após a chamada.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'cliente_nome' => ['type' => 'string',           'description' => 'Nome do cliente'],
                        'profissional_id' => ['type' => 'integer',          'description' => 'ID do profissional'],
                        'servico_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'IDs de todos os serviços do atendimento, na ordem (ex.: [corte, barba])'],
                        'data' => ['type' => 'string',           'description' => 'Data no formato YYYY-MM-DD'],
                        'horario' => ['type' => 'string',           'description' => 'Horário no formato HH:MM'],
                        'opcao_extra' => ['type' => ['string', 'null'], 'description' => 'Opção extra (opcional)'],
                        'observacoes' => ['type' => ['string', 'null'], 'description' => 'Observações (opcional)'],
                    ],
                    'required' => ['cliente_nome', 'profissional_id', 'servico_ids', 'data', 'horario'],
                ],
            ],
            [
                'name' => 'confirmar_agendamento',
                'description' => 'Confirma o agendamento pendente existente quando o cliente expressa confirmação.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'cancelar_agendamento',
                'description' => 'Cancela o agendamento pendente existente quando o cliente solicita cancelamento.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'listar_agendamentos_cliente',
                'description' => 'Lista os próximos agendamentos do cliente atual. DEVE ser chamada antes de reagendar_agendamento para identificar qual agendamento o cliente quer alterar.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'reagendar_agendamento',
                'description' => 'Atualiza um agendamento existente com nova data, hora e opcionalmente novo profissional ou serviço. OBRIGATÓRIO chamar listar_agendamentos_cliente antes para obter o agendamento_id correto. Use buscar_slots para confirmar disponibilidade antes de chamar esta ferramenta.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['agendamento_id', 'data', 'hora'],
                    'properties' => [
                        'agendamento_id' => ['type' => 'integer', 'description' => 'ID do agendamento a alterar (obtido via listar_agendamentos_cliente)'],
                        'data' => ['type' => 'string',  'description' => 'Nova data no formato YYYY-MM-DD'],
                        'hora' => ['type' => 'string',  'description' => 'Novo horário no formato HH:MM'],
                        'profissional_id' => ['type' => 'integer', 'description' => 'Novo profissional (opcional — mantém o atual se omitido)'],
                        'servico_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Novos serviços (opcional — mantém os atuais se omitido)'],
                    ],
                ],
            ],
            [
                'name' => 'transferir_para_humano',
                'description' => 'Transfere a conversa para um atendente humano. Use quando não entender o cliente após 2 tentativas, quando pedir humano ou ficar irritado.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass],
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];

        $foraDoContexto = $temAgendamentoPendente
            ? ['criar_agendamento']
            : ['confirmar_agendamento', 'cancelar_agendamento', 'listar_agendamentos_cliente', 'reagendar_agendamento'];

        return array_values(array_filter($tools, fn ($tool) => ! in_array($tool['name'], $foraDoContexto, true)));
    }

    private function buildToolsTriagem(): array
    {
        return [
            [
                'name' => 'transferir_para_humano',
                'description' => 'Encaminha a conversa para uma atendente humana finalizar o agendamento. Chame assim que tiver: nome do cliente, serviço/motivo desejado e a preferência de dia/horário. NUNCA confirme horário ou agendamento antes — quem confere a agenda é a atendente.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'resumo' => ['type' => 'string', 'description' => 'Resumo curto do que o cliente deseja (serviço/motivo + preferência de dia e horário)'],
                        'nome_cliente' => ['type' => 'string', 'description' => 'Nome do cliente'],
                        'preferencia' => ['type' => 'string', 'description' => 'Preferência de dia/horário informada pelo cliente'],
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
    private function buildAtendimentoBlock(Tenant $tenant, Carbon $agora): string
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

    /**
     * A confirmação de handoff precisa ser estável e enviada uma única vez. Uma
     * mensagem configurada pelo estabelecimento tem prioridade; sem ela, ajustamos
     * o texto ao horário de atendimento, sem expor uma resposta gerada pela IA.
     */
    private function mensagemTransferencia(Tenant $tenant, Carbon $agora): string
    {
        $configurada = trim((string) ($tenant->triagemConfig()['mensagem_transferencia'] ?? ''));
        if ($configurada !== '') {
            return $configurada;
        }

        if (! $tenant->emHorarioAtendimento($agora)) {
            $foraHorario = trim((string) $tenant->mensagem_fora_horario);
            if ($foraHorario !== '') {
                return $foraHorario;
            }

            $horario = $tenant->horarioAtendimentoTexto();

            return $horario !== ''
                ? "Recebi sua solicitação. Uma atendente retornará no horário de atendimento ({$horario})."
                : 'Recebi sua solicitação. Uma atendente retornará assim que possível.';
        }

        return 'Recebi sua solicitação. Vou encaminhar para uma atendente, que dará sequência em instantes.';
    }

    /**
     * Quando o cliente não disse o nome, o modelo costuma preencher o campo
     * obrigatório com algo genérico ("Cliente", "Não informado") ou com o telefone.
     */
    private function nomeRealInformado(mixed $nome): ?string
    {
        $nome = trim((string) $nome);
        $normalizado = mb_strtolower($nome);

        if ($nome === ''
            || preg_match('/^\+?[\d\s().-]+$/', $nome) === 1
            || in_array($normalizado, self::NOMES_GENERICOS, true)) {
            return null;
        }

        return $nome;
    }

    /** Resposta montada com os dados gravados, usada quando o texto da IA é descartado. */
    private function respostaDaAcaoConcluida(Tenant $tenant): ?string
    {
        if (! $this->acaoConcluida) {
            return null;
        }

        $agendamento = $this->acaoConcluida['agendamento'];
        $quando = Carbon::parse($agendamento->data_hora ?? $agendamento->inicio)
            ->setTimezone($tenant->resolvedTimezone());
        $dataHora = $quando->format('d/m').' às '.$quando->format('H:i');
        $detalhe = collect([
            $agendamento->descricaoServicos(),
            $agendamento->profissional?->nome ? 'com '.$agendamento->profissional->nome : null,
        ])->filter()->join(' ');
        $detalhe = $detalhe !== '' ? "{$detalhe} em {$dataHora}" : $dataHora;

        return match ($this->acaoConcluida['tipo']) {
            'criado' => "Agendamento confirmado! ✅\n{$detalhe}. Te esperamos!",
            'reagendado' => "Pronto! Seu horário foi alterado: {$detalhe}. ✅",
            'confirmado' => "Presença confirmada! ✅\n{$detalhe}. Te esperamos!",
            'cancelado' => "Pronto, seu horário de {$dataHora} foi cancelado.",
            default => null,
        };
    }

    private function respostaIndicaTransferencia(string $resposta): bool
    {
        return preg_match(
            '/\b(transferi|transferimos|transferindo|encaminhei|encaminhamos|encaminhando)\b|\bwe have transferred\b|atendente humano.*\b(dispon[ií]vel|a caminho)\b/iu',
            $resposta,
        ) === 1;
    }

    private function respostaAfirmaNovoAgendamento(string $resposta): bool
    {
        return preg_match(
            '/\b(est[aá]\s+(agendad[oa]|confirmad[oa]|reservad[oa])|agendamento\s+(confirmad[oa]|realizad[oa])|reserva\s+(confirmad[oa]|realizad[oa]))\b/iu',
            $resposta,
        ) === 1;
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
            ->map(fn ($s) => "- {$s->nome}".
                ($s->valor_min ? " (R$ {$s->valor_min}".($s->valor_max ? "-{$s->valor_max}" : '').')' : '').
                ($s->requer_avaliacao ? ' [requer avaliação]' : ''))
            ->join("\n");

        $tomInstrucao = match ($tenant->tom_voz) {
            'formal' => 'Linguagem profissional e respeitosa. Sem emojis. Use "Senhor/Senhora".',
            'descontraido' => 'Linguagem leve e simpática. Emojis liberados. Pode usar gírias suaves.',
            default => 'Linguagem clara e amigável. Emojis com moderação. Tratamento informal mas respeitoso.',
        };

        $instrucoes = $tenant->instrucoes_extras
            ? "\nINSTRUÇÕES ESPECÍFICAS DO NEGÓCIO (têm prioridade sobre o FLUXO padrão abaixo em caso de conflito):\n{$tenant->instrucoes_extras}"
            : '';
        $saudacao = $tenant->bot_saudacao ? "\nMENSAGEM DE BOAS-VINDAS (use como referência ao saudar o cliente pela primeira vez):\n{$tenant->bot_saudacao}" : '';
        $servicosPart = $servicos ? "\nSERVIÇOS OFERECIDOS (apenas para contexto):\n{$servicos}\n" : '';
        $profPart = $profissionais ? "\nPROFISSIONAIS (apenas para contexto):\n{$profissionais}\n" : '';

        return <<<PROMPT
Você é {$tenant->nome_agente} de {$tenant->nome} ({$tenant->ramo_negocio}). {$tenant->descricao_negocio}
Local: {$tenant->endereco}, {$tenant->cidade} | Tom: {$tomInstrucao}

SEU PAPEL: Fazer o PRÉ-ATENDIMENTO. Você NÃO agenda e NÃO tem acesso à agenda — a disponibilidade é conferida por uma atendente humana. Seu trabalho é acolher o cliente, entender o que ele precisa e coletar os dados para a atendente concluir.
{$servicosPart}{$profPart}{$saudacao}{$instrucoes}

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
- Mensagem iniciada por [áudio transcrito] é um áudio do cliente já convertido em texto: trate como texto normal.
- Outras mídias → peça para descrever em texto.
PROMPT;
    }

    public function buildStaticPrompt(Tenant $tenant, bool $clienteTemAgendamento = true): string
    {
        $servicosAtivos = $tenant->servicos()->where('ativo', true)->orderBy('nome')->get()->keyBy('id');
        $real = fn ($valor) => 'R$ '.number_format((float) $valor, 2, ',', '.');
        $precoBase = function ($s) use ($real) {
            if ($s->valor_min === null) {
                return null;
            }
            $max = $s->valor_max !== null && abs((float) $s->valor_max - (float) $s->valor_min) >= 0.01
                ? ' a '.$real($s->valor_max) : '';

            return $real($s->valor_min).$max;
        };

        // Cada profissional com o que faz e, quando diferente do padrão, o preço/duração dele.
        $profissionais = $tenant->profissionais()->where('ativo', true)->whereHas('horarios')->orderBy('nome')->with('servicos')->get()
            ->map(function ($p) use ($servicosAtivos, $real) {
                $base = "- ID {$p->id}: {$p->nome}";
                $itens = $p->servicos->filter(fn ($s) => $servicosAtivos->has($s->id))->map(function ($s) use ($real) {
                    $extras = array_filter([
                        $s->pivot->valor !== null ? $real($s->pivot->valor) : null,
                        $s->pivot->duracao_minutos ? "{$s->pivot->duracao_minutos}min" : null,
                    ]);

                    return $s->nome.($extras ? ' ('.implode(', ', $extras).')' : '');
                });

                return $itens->isNotEmpty() ? "{$base} → ".$itens->join(', ') : "{$base} → faz todos os serviços";
            })
            ->join("\n");

        $servicos = $servicosAtivos
            ->map(fn ($s) => "- ID {$s->id}: {$s->nome}".
                (($preco = $precoBase($s)) ? " ({$preco})" : '').
                " — {$s->duracao_minutos}min".
                ($s->requer_avaliacao ? ' [requer avaliação]' : ''))
            ->join("\n");

        $opcoes = $tenant->opcoes_extras()->where('ativo', true)->get()
            ->groupBy('tipo')
            ->map(fn ($grupo, $tipo) => strtoupper($tipo).': '.$grupo->pluck('nome')->join(', '))
            ->join("\n");

        $horarios = $this->formatarHorarios($tenant->horarios_funcionamento);

        $tomInstrucao = match ($tenant->tom_voz) {
            'formal' => 'Linguagem profissional e respeitosa. Sem emojis. Use "Senhor/Senhora".',
            'descontraido' => 'Linguagem leve e simpática. Emojis liberados. Pode usar gírias suaves.',
            default => 'Linguagem clara e amigável. Emojis com moderação. Tratamento informal mas respeitoso.',
        };

        $instrucoes = $tenant->instrucoes_extras
            ? "\nINSTRUÇÕES ESPECÍFICAS DO NEGÓCIO (têm prioridade sobre o FLUXO padrão abaixo em caso de conflito):\n{$tenant->instrucoes_extras}"
            : '';
        $saudacao = $tenant->bot_saudacao ? "\nMENSAGEM DE BOAS-VINDAS (use como referência ao saudar o cliente pela primeira vez):\n{$tenant->bot_saudacao}" : '';
        $opcoesPart = $opcoes ? "\n{$opcoes}\n" : '';
        $maxTentativas = $tenant->triagemConfig()['max_tentativas_sem_entender'];
        $apresentacao = trim("{$tenant->nome_agente} de {$tenant->nome}".($tenant->ramo_negocio ? " ({$tenant->ramo_negocio})" : '').". {$tenant->descricao_negocio}");
        $local = collect([$tenant->endereco, $tenant->cidade])->filter()->join(', ');
        $localPart = $local !== '' ? "Local: {$local} | " : '';

        $variosServicos = $servicosAtivos->count() > 1 ? <<<'TXT'

VÁRIOS SERVIÇOS NO MESMO HORÁRIO:
- Se existir na lista um serviço que já junta o que o cliente pediu (ex.: "Corte + Barba"), use esse serviço.
- Senão, passe todos os IDs em servico_ids (buscar_slots e criar_agendamento): o sistema soma duração e preço.
- Ao informar preço, use o valor que buscar_slots retornou para o profissional escolhido.

TXT : "\n";

        // O fluxo de reagendamento só interessa a quem já tem horário marcado; para os
        // demais, dizemos explicitamente que não há agendamento (evita "cancelei" inventado).
        $gestao = $clienteTemAgendamento ? <<<'TXT'
FLUXO OBRIGATÓRIO PARA REAGENDAMENTO:
1. Chame listar_agendamentos_cliente → mostre a lista ao cliente
2. Cliente escolhe qual quer alterar → se ele ainda não disse dia e período (manhã/tarde), pergunte antes de buscar horários
3. Chame buscar_slots e ofereça só 2-3 horários dentro do período escolhido (NUNCA liste todos os horários retornados)
4. Cliente confirma novo horário → chame reagendar_agendamento com o agendamento_id e os novos dados
5. reagendar_agendamento sucesso=true → confirme o novo horário ao cliente
- Se retornar horario_indisponivel: chame buscar_slots novamente e ofereça alternativas dentro do mesmo período
TXT : 'O cliente não tem agendamento futuro. Se ele pedir para cancelar, remarcar ou confirmar um horário, diga que não encontrou agendamento ativo no nome dele e ofereça marcar um novo.';

        return <<<PROMPT
Você é {$apresentacao}
{$localPart}Horários: {$horarios} | Tom: {$tomInstrucao}

PROFISSIONAIS (preço/duração entre parênteses valem só para aquele profissional):
{$profissionais}

SERVIÇOS (preço e duração padrão):
{$servicos}{$opcoesPart}{$saudacao}{$instrucoes}
{$variosServicos}{$gestao}

FLUXO OBRIGATÓRIO PARA NOVO AGENDAMENTO:
1. Se o cliente ainda não disse dia e período (manhã/tarde) que prefere, pergunte antes de buscar horários
2. Chame buscar_slots para conferir a disponibilidade real
3. NUNCA despeje a lista inteira retornada — ofereça só 2-3 horários dentro do período escolhido (ex.: "Na segunda de manhã tenho 09:00, 10:00 e 11:00, algum serve?"). Se o cliente quiser ver mais opções, ofereça outras dentro do mesmo critério
4. Cliente escolhe → confirme em texto: "Ok! [serviço] com [profissional] em [data] às [hora], certo?"
5. Cliente confirma → chame criar_agendamento com os dados EXATOS do slot escolhido
6. criar_agendamento retorna sucesso=true → informe o cliente que está agendado

REGRAS:
- Mensagens curtas (máx 3 linhas)
- JAMAIS invente horários: use SOMENTE as horas retornadas por buscar_slots
- JAMAIS liste de uma vez todos os horários que buscar_slots retornou — sempre resuma e pergunte a preferência do cliente antes
- Se criar_agendamento retornar horario_indisponivel: chame buscar_slots novamente e ofereça alternativas
- Mensagem iniciada por [áudio transcrito] é um áudio do cliente já convertido em texto: responda ao conteúdo normalmente
- Outras mídias ([áudio], [imagem], [vídeo], [figurinha]) → diga que não conseguiu ouvir/ver e peça para escrever
- {$maxTentativas} mensagens sem entender / cliente irritado → chame transferir_para_humano
- Pode agendar para HOJE quando o horário vier de buscar_slots (ele já respeita a antecedência mínima). Nunca agende no passado

CRÍTICO: NUNCA diga que um agendamento foi criado/confirmado sem antes ter chamado criar_agendamento com sucesso. O sistema só registra via ferramenta — mensagem de texto não cria agendamento.
PROMPT;
    }

    private function formatarHorarios(mixed $horarios): string
    {
        if (empty($horarios)) {
            return 'Consultar pelo WhatsApp';
        }
        if (is_string($horarios)) {
            return $horarios;
        }

        return collect($horarios)->map(fn ($h, $k) => "{$k}: {$h}")->join(' | ');
    }
}
