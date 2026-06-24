<?php

namespace App\Services;

use App\Models\Conversa;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

class ClaudeService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.claude.key');
        $this->model  = (string) config('services.claude.model');
    }

    /**
     * Processa mensagem do cliente e retorna resposta + próxima etapa.
     *
     * @return array{resposta: string, proxima_etapa: string, dados_extraidos: array}
     */
    public function processarMensagem(
        Tenant $tenant,
        Conversa $conversa,
        string $mensagemCliente,
    ): array {
        [$staticPrompt, $dynamicPrompt] = $this->montarSystemPrompt($tenant, $conversa);

        $systemBlocks = [
            [
                'type'          => 'text',
                'text'          => $staticPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ],
            [
                'type' => 'text',
                'text' => $dynamicPrompt,
            ],
        ];

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'anthropic-beta'    => 'prompt-caching-2024-07-31',
            'content-type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->model,
            'max_tokens' => 300,
            'system'     => $systemBlocks,
            'messages'   => array_merge(
                $conversa->historico_mensagens ?? [],
                [['role' => 'user', 'content' => $mensagemCliente]],
            ),
        ]);

        $content = $response->json('content.0.text');
        $parsed  = json_decode((string) $content, true);

        return [
            'resposta'        => $parsed['resposta']        ?? '',
            'proxima_etapa'   => $parsed['proxima_etapa']   ?? $conversa->etapa,
            'dados_extraidos' => $parsed['dados']           ?? [],
        ];
    }

    /** @return array{0: string, 1: string} [staticPrompt, dynamicPrompt] */
    private function montarSystemPrompt(Tenant $tenant, Conversa $conversa): array
    {
        $recursos = $tenant->recursos()->where('ativo', true)->get()
            ->map(fn ($r) => "- ID {$r->id}: {$r->nome} (R$ {$r->valor_hora}/h)")
            ->join("\n");

        $static = <<<PROMPT
Você é o assistente de agendamento de "{$tenant->nome}", um(a) {$tenant->tipo_servico}.
Seu objetivo é ajudar o cliente a fazer um agendamento de forma rápida e simpática.

RECURSOS DISPONÍVEIS:
{$recursos}

FLUXO:
1. idle → saudação e perguntar o que deseja
2. escolhendo_recurso → mostrar recursos disponíveis e capturar escolha
3. escolhendo_data → sugerir dias da semana com disponibilidade e capturar data
4. escolhendo_horario → listar slots disponíveis e capturar horário
5. confirmando → resumir e pedir nome para confirmar
6. concluido → confirmar agendamento com detalhes

REGRAS:
- Seja breve e direto (máximo 3 linhas por mensagem)
- Use emojis moderadamente
- Se o cliente mencionar recurso + data + horário na mesma mensagem, avance várias etapas
- NUNCA invente horários disponíveis; use apenas os fornecidos no contexto
- Se não entender, peça gentilmente para repetir

RESPONDA SEMPRE EM JSON com esta estrutura:
{
  "resposta": "mensagem para o cliente",
  "proxima_etapa": "idle|escolhendo_recurso|escolhendo_data|escolhendo_horario|confirmando|concluido",
  "dados": {
    "recurso_id": null,
    "data": null,
    "horario": null,
    "nome_cliente": null
  }
}
PROMPT;

        $contexto   = json_encode($conversa->contexto ?? [], JSON_UNESCAPED_UNICODE);
        $etapaAtual = $conversa->etapa;
        $dynamic    = "ETAPA ATUAL DA CONVERSA: {$etapaAtual}\nCONTEXTO PARCIAL: {$contexto}";

        return [$static, $dynamic];
    }
}
