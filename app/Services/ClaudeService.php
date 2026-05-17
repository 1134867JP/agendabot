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
        $systemPrompt = $this->montarSystemPrompt($tenant, $conversa);

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->model,
            'max_tokens' => 500,
            'system'     => $systemPrompt,
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

    private function montarSystemPrompt(Tenant $tenant, Conversa $conversa): string
    {
        $recursos = $tenant->recursos()->where('ativo', true)->get()
            ->map(fn ($r) => "- ID {$r->id}: {$r->nome} (R$ {$r->valor_hora}/h)")
            ->join("\n");

        $contexto   = json_encode($conversa->contexto ?? [], JSON_UNESCAPED_UNICODE);
        $etapaAtual = $conversa->etapa;

        return <<<PROMPT
Você é o assistente de agendamento de "{$tenant->nome}", um(a) {$tenant->tipo_servico}.
Seu objetivo é ajudar o cliente a fazer um agendamento de forma rápida e simpática.

RECURSOS DISPONÍVEIS:
{$recursos}

ETAPA ATUAL DA CONVERSA: {$etapaAtual}
CONTEXTO PARCIAL: {$contexto}

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
    }
}
