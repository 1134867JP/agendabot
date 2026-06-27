<?php

// app/Services/ClaudeAgentService.php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAgentService
{
    private string $apiKey;
    private string $model;

    // Context set per-call (safe: queue workers are single-threaded)
    private Tenant $currentTenant;
    private array $currentCliente; // ['id', 'nome', 'telefone']
    private bool $transferir;

    public function __construct(private AgendamentoService $agendamentoService)
    {
        $this->apiKey = (string) config('services.claude.key');
        $this->model  = (string) config('services.claude.model');
    }

    /**
     * @param array $mensagens     [['role'=>'user'|'assistant', 'content'=>string], ...]
     * @param array $clienteInfo   ['id'=>int, 'nome'=>string, 'telefone'=>string]
     * @return array{resposta: string, transferir: bool, usage: array}
     */
    public function processar(
        Tenant $tenant,
        array $mensagens,
        array $clienteInfo,
        ?Agendamento $agendamentoPendente = null,
    ): array {
        $this->currentTenant  = $tenant;
        $this->currentCliente = $clienteInfo;
        $this->transferir     = false;

        $totalUsage = [
            'input_tokens'                => 0,
            'output_tokens'               => 0,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens'     => 0,
        ];

        $system   = $this->buildSystem($tenant, $agendamentoPendente);
        $tools    = $this->getTools();
        $messages = $mensagens;

        $finalText     = null;
        $maxIterations = 6;

        for ($i = 0; $i < $maxIterations; $i++) {
            $response = Http::timeout(30)
                ->retry(2, 1000, fn ($e) =>
                    $e instanceof \Illuminate\Http\Client\RequestException && $e->response->status() === 529
                )
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'anthropic-beta'    => 'prompt-caching-2024-07-31',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->model,
                    'max_tokens' => 1024,
                    'system'     => $system,
                    'tools'      => $tools,
                    'messages'   => $messages,
                ]);

            if (! $response->successful()) {
                Log::error('ClaudeAgentService error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'iter'   => $i,
                ]);
                break;
            }

            $usage = $response->json('usage', []);
            $totalUsage['input_tokens']                += (int) ($usage['input_tokens']                ?? 0);
            $totalUsage['output_tokens']               += (int) ($usage['output_tokens']               ?? 0);
            $totalUsage['cache_creation_input_tokens'] += (int) ($usage['cache_creation_input_tokens'] ?? 0);
            $totalUsage['cache_read_input_tokens']     += (int) ($usage['cache_read_input_tokens']     ?? 0);

            Log::debug("ClaudeAgentService iter {$i}", [
                'stop_reason'    => $response->json('stop_reason'),
                'cache_creation' => $usage['cache_creation_input_tokens'] ?? 0,
                'cache_read'     => $usage['cache_read_input_tokens']     ?? 0,
                'input'          => $usage['input_tokens']                ?? 0,
                'output'         => $usage['output_tokens']               ?? 0,
            ]);

            $stopReason = $response->json('stop_reason');
            $content    = $response->json('content', []);

            if ($stopReason === 'end_turn') {
                foreach ($content as $block) {
                    if ($block['type'] === 'text') {
                        $finalText = $block['text'];
                        break;
                    }
                }
                break;
            }

            if ($stopReason === 'tool_use') {
                // Append assistant turn
                $messages[] = ['role' => 'assistant', 'content' => $content];

                // Execute each tool call
                $toolResults = [];
                foreach ($content as $block) {
                    if ($block['type'] !== 'tool_use') continue;

                    $result = $this->executeTool($block['name'], $block['input'] ?? []);
                    $toolResults[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content'     => $result,
                    ];
                }

                $messages[] = ['role' => 'user', 'content' => $toolResults];
                continue;
            }

            // stop_reason === 'max_tokens' or unknown
            break;
        }

        return [
            'resposta'   => $finalText ?? 'Desculpe, tive um problema técnico. Tente novamente em instantes.',
            'transferir' => $this->transferir,
            'usage'      => $totalUsage,
        ];
    }

    // -------------------------------------------------------------------------
    // Tool execution
    // -------------------------------------------------------------------------

    private function executeTool(string $name, array $input): string
    {
        try {
            return match ($name) {
                'buscar_slots'           => $this->toolBuscarSlots($input),
                'criar_agendamento'      => $this->toolCriarAgendamento($input),
                'confirmar_agendamento'  => $this->toolConfirmarAgendamento($input),
                'cancelar_agendamento'   => $this->toolCancelarAgendamento($input),
                'transferir_para_humano' => $this->toolTransferirParaHumano(),
                default => "Ferramenta desconhecida: {$name}",
            };
        } catch (\Throwable $e) {
            Log::warning("ClaudeAgentService tool '{$name}' exception", ['error' => $e->getMessage()]);
            return "Erro ao executar {$name}: " . $e->getMessage();
        }
    }

    private function toolBuscarSlots(array $input): string
    {
        $profissionalId = (int) ($input['profissional_id'] ?? 0);
        $dias = min((int) ($input['dias'] ?? 7), 14);

        $profissional = Profissional::where('id', $profissionalId)
            ->where('tenant_id', $this->currentTenant->id)
            ->where('ativo', true)
            ->first();

        if (! $profissional) {
            return "Profissional ID {$profissionalId} não encontrado ou inativo.";
        }

        $linhas = [];
        for ($i = 0; $i < $dias; $i++) {
            $data = Carbon::today()->addDays($i);
            $slots = $profissional->slotsDisponiveis($data);
            $disponiveis = collect($slots)->where('disponivel', true)->pluck('hora')->values()->all();
            if (! empty($disponiveis)) {
                $linhas[] = $data->format('Y-m-d') . ' (' . $data->locale('pt_BR')->isoFormat('ddd') . '): '
                    . implode(' ', $disponiveis);
            }
        }

        if (empty($linhas)) {
            return "Nenhum horário disponível nos próximos {$dias} dias para {$profissional->nome}.";
        }

        return "{$profissional->nome} — slots disponíveis:\n" . implode("\n", $linhas);
    }

    private function toolCriarAgendamento(array $input): string
    {
        $agendamento = $this->agendamentoService->criarAgendamentoV2(
            $this->currentTenant,
            array_merge($input, [
                'cliente_id'       => $this->currentCliente['id'],
                'cliente_nome'     => $this->currentCliente['nome'],
                'cliente_telefone' => $this->currentCliente['telefone'],
                'origem'           => 'bot',
            ]),
        );

        $dataHora = Carbon::parse($agendamento->data_hora)
            ->locale('pt_BR')
            ->translatedFormat('d/m/Y \à\s H:i');

        return "Agendamento criado com sucesso! ID #{$agendamento->id} — {$dataHora}.";
    }

    private function toolConfirmarAgendamento(array $input): string
    {
        $id = (int) ($input['agendamento_id'] ?? 0);

        $agendamento = Agendamento::where('id', $id)
            ->where('tenant_id', $this->currentTenant->id)
            ->first();

        if (! $agendamento) {
            return "Agendamento #{$id} não encontrado.";
        }

        $agendamento->update(['status' => 'confirmado']);

        return "Agendamento #{$id} confirmado com sucesso.";
    }

    private function toolCancelarAgendamento(array $input): string
    {
        $id = (int) ($input['agendamento_id'] ?? 0);

        $agendamento = Agendamento::where('id', $id)
            ->where('tenant_id', $this->currentTenant->id)
            ->first();

        if (! $agendamento) {
            return "Agendamento #{$id} não encontrado.";
        }

        $this->agendamentoService->cancelar($agendamento);

        return "Agendamento #{$id} cancelado com sucesso.";
    }

    private function toolTransferirParaHumano(): string
    {
        $this->transferir = true;
        return "Transferência para atendente humano solicitada.";
    }

    // -------------------------------------------------------------------------
    // Tool definitions (Anthropic tool use format)
    // -------------------------------------------------------------------------

    private function getTools(): array
    {
        return [
            [
                'name'         => 'buscar_slots',
                'description'  => 'Busca os horários disponíveis de um profissional nos próximos dias. Use sempre antes de oferecer horários ao cliente.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'profissional_id' => [
                            'type'        => 'integer',
                            'description' => 'ID do profissional (ver lista de profissionais no system prompt)',
                        ],
                        'dias' => [
                            'type'        => 'integer',
                            'description' => 'Quantos dias à frente verificar (padrão: 7, máximo: 14)',
                        ],
                    ],
                    'required' => ['profissional_id'],
                ],
            ],
            [
                'name'         => 'criar_agendamento',
                'description'  => 'Cria um agendamento para o cliente. Use apenas quando tiver confirmação explícita do cliente com data e horário específicos.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'profissional_id' => ['type' => 'integer', 'description' => 'ID do profissional'],
                        'servico_id'      => ['type' => 'integer', 'description' => 'ID do serviço (opcional)'],
                        'data'            => ['type' => 'string', 'description' => 'Data no formato YYYY-MM-DD'],
                        'horario'         => ['type' => 'string', 'description' => 'Horário no formato HH:MM'],
                        'opcao_extra'     => ['type' => 'string', 'description' => 'Opção extra escolhida (opcional)'],
                        'observacoes'     => ['type' => 'string', 'description' => 'Observações (opcional)'],
                    ],
                    'required' => ['profissional_id', 'data', 'horario'],
                ],
            ],
            [
                'name'         => 'confirmar_agendamento',
                'description'  => 'Confirma um agendamento existente quando o cliente responde positivamente (ex: "confirmo", "sim", "ok", "✅").',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'agendamento_id' => ['type' => 'integer', 'description' => 'ID do agendamento a confirmar'],
                    ],
                    'required' => ['agendamento_id'],
                ],
            ],
            [
                'name'         => 'cancelar_agendamento',
                'description'  => 'Cancela um agendamento existente quando o cliente solicita cancelamento (ex: "cancelo", "não quero mais", "❌").',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'agendamento_id' => ['type' => 'integer', 'description' => 'ID do agendamento a cancelar'],
                    ],
                    'required' => ['agendamento_id'],
                ],
            ],
            [
                'name'         => 'transferir_para_humano',
                'description'  => 'Transfere a conversa para um atendente humano. Use quando o cliente pedir explicitamente, ficar irritado, ou após 2 tentativas sem entender.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // System prompt
    // -------------------------------------------------------------------------

    private function buildSystem(Tenant $tenant, ?Agendamento $agendamentoPendente): array
    {
        $blocks = [
            [
                'type'          => 'text',
                'text'          => $this->buildStaticPrompt($tenant),
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];

        if ($agendamentoPendente) {
            $dataHora = Carbon::parse($agendamentoPendente->data_hora ?? $agendamentoPendente->inicio)
                ->locale('pt_BR')
                ->translatedFormat('d/m/Y \à\s H:i');

            $nomeProfissional = $agendamentoPendente->profissional?->nome ?? 'profissional';

            $blocks[] = [
                'type' => 'text',
                'text' => "AGENDAMENTO PENDENTE DO CLIENTE: ID #{$agendamentoPendente->id} — {$dataHora} com {$nomeProfissional} (status: {$agendamentoPendente->status}).\n"
                    . "Se o cliente confirmar (\"confirmo\", \"sim\", \"ok\", \"✅\"), use confirmar_agendamento com id={$agendamentoPendente->id}.\n"
                    . "Se o cliente cancelar (\"cancelo\", \"não quero\", \"❌\"), use cancelar_agendamento com id={$agendamentoPendente->id}.",
            ];
        }

        return $blocks;
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

        $horarios = $this->formatarHorarios($tenant->horarios_funcionamento ?? []);

        $tomInstrucao = match ($tenant->tom_voz) {
            'formal'       => 'Linguagem profissional e respeitosa. Sem emojis. Use "Senhor/Senhora".',
            'descontraido' => 'Linguagem leve e simpática. Emojis liberados. Pode usar gírias suaves.',
            default        => 'Linguagem clara e amigável. Emojis com moderação. Tratamento informal mas respeitoso.',
        };

        $instrucoes = $tenant->instrucoes_extras ? "\nINSTRUÇÕES ESPECÍFICAS DO NEGÓCIO:\n{$tenant->instrucoes_extras}" : '';
        $opcoesPart = $opcoes ? "\nOPÇÕES EXTRAS:\n{$opcoes}\n" : '';

        return <<<PROMPT
Você é {$tenant->nome_agente} de {$tenant->nome} ({$tenant->ramo_negocio}).
{$tenant->descricao_negocio}
Local: {$tenant->endereco}, {$tenant->cidade} | Horários: {$horarios}
Tom: {$tomInstrucao}

PROFISSIONAIS (use o ID nas ferramentas):
{$profissionais}

SERVIÇOS (use o ID nas ferramentas):
{$servicos}{$opcoesPart}{$instrucoes}

INSTRUÇÕES:
- Respostas curtas e diretas
- Use buscar_slots antes de oferecer qualquer horário — nunca invente disponibilidade
- Só chame criar_agendamento quando o cliente confirmar explicitamente data e horário
- Mensagens com mídia: peça que o cliente descreva em texto
- Após 2 tentativas sem entender: use transferir_para_humano
- Cliente irritado ou pediu atendente: use transferir_para_humano
PROMPT;
    }

    private function formatarHorarios(array $horarios): string
    {
        if (empty($horarios)) return 'Consultar pelo WhatsApp';
        return collect($horarios)->map(fn ($h, $k) => "{$k}: {$h}")->join(' | ');
    }
}
