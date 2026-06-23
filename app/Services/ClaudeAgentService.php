<?php

// app/Services/ClaudeAgentService.php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAgentService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.claude.key');
        $this->model  = (string) config('services.claude.model');
    }

    /**
     * @param array $mensagens [['role'=>'user'|'assistant', 'content'=>string], ...]
     * @param array $horariosDisponiveis resultado de AgendamentoService::buscarHorariosDisponiveis()
     * @return array{acao: string, resposta: string, dados: array}
     */
    public function processar(Tenant $tenant, array $mensagens, array $horariosDisponiveis): array
    {
        $systemPrompt = $this->buildSystemPrompt($tenant, $horariosDisponiveis);

        $response = Http::timeout(30)
            ->retry(2, 1000, function ($e) {
                if ($e instanceof \Illuminate\Http\Client\RequestException) {
                    return $e->response->status() === 529;
                }
                return false;
            })
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 600,
                'system'     => $systemPrompt,
                'messages'   => $mensagens,
            ]);

        if (! $response->successful()) {
            Log::error('ClaudeAgentService error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['acao' => 'erro', 'resposta' => 'Desculpe, tive um problema técnico. Tente novamente em instantes.', 'dados' => []];
        }

        $content = $response->json('content.0.text', '');

        // Tentar extrair JSON: iterar todas as ocorrências e usar o primeiro que decodifica com sucesso
        $jsonDecoded = null;
        if (preg_match_all('/\{[\s\S]*?"acao"[\s\S]*?\}/u', $content, $allMatches)) {
            foreach ($allMatches[0] as $candidate) {
                $decoded = json_decode($candidate, true);
                if (is_array($decoded) && isset($decoded['acao'], $decoded['resposta'])) {
                    $jsonDecoded = $decoded;
                    break;
                }
            }
        }

        if ($jsonDecoded) {
            return [
                'acao'    => $jsonDecoded['acao'],
                'resposta' => $jsonDecoded['resposta'],
                'dados'   => array_diff_key($jsonDecoded, ['acao' => 1, 'resposta' => 1]),
            ];
        }

        return ['acao' => 'duvida', 'resposta' => $content, 'dados' => []];
    }

    public function buildSystemPrompt(Tenant $tenant, array $horariosDisponiveis): string
    {
        $profissionais = $tenant->profissionais()->where('ativo', true)->get()
            ->map(fn ($p) => "- ID {$p->id}: {$p->nome}" . ($p->especialidades ? ' (' . implode(', ', $p->especialidades) . ')' : ''))
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

        $slotsFormatados = $this->formatarSlots($horariosDisponiveis);

        $tomInstrucao = match ($tenant->tom_voz) {
            'formal'      => 'Linguagem profissional e respeitosa. Sem emojis. Use "Senhor/Senhora".',
            'descontraido' => 'Linguagem leve e simpática. Emojis liberados. Pode usar gírias suaves.',
            default       => 'Linguagem clara e amigável. Emojis com moderação. Tratamento informal mas respeitoso.',
        };

        $instrucoes = $tenant->instrucoes_extras ? "\nINSTRUÇÕES ESPECÍFICAS DO NEGÓCIO:\n{$tenant->instrucoes_extras}" : '';

        return <<<PROMPT
Você é {$tenant->nome_agente}, assistente virtual de {$tenant->nome}.
{$tenant->descricao_negocio}

Ramo: {$tenant->ramo_negocio}
Endereço: {$tenant->endereco}, {$tenant->cidade}
Horários de funcionamento: {$horarios}

TOM DE VOZ: {$tomInstrucao}

PROFISSIONAIS DISPONÍVEIS:
{$profissionais}

SERVIÇOS DISPONÍVEIS:
{$servicos}

{$opcoes}

HORÁRIOS DISPONÍVEIS — PRÓXIMOS 7 DIAS:
{$slotsFormatados}

REGRAS:
- Nunca invente horários — use apenas os fornecidos acima
- Mensagens curtas (WhatsApp, não e-mail)
- Após 2 tentativas sem entender o cliente, transfira para humano
- Não faça diagnósticos ou promessas de resultado
{$instrucoes}

QUANDO TRANSFERIR PARA HUMANO:
- Cliente irritado ou reclamando
- Dúvida fora do seu escopo após 2 tentativas
- Cliente pedir explicitamente para falar com pessoa

QUANDO UMA AÇÃO FOR CONFIRMADA, retorne PRIMEIRO o JSON depois a mensagem:
{"acao":"agendar","cliente_nome":"...","profissional_id":123,"servico_id":456,"data":"YYYY-MM-DD","horario":"HH:MM","opcao_extra":null,"observacoes":"...","resposta":"mensagem para o cliente"}

Para transferência:
{"acao":"transferir","resposta":"mensagem para o cliente"}

Para apenas responder (sem ação):
{"acao":"duvida","resposta":"mensagem para o cliente"}
PROMPT;
    }

    private function formatarHorarios(array $horarios): string
    {
        if (empty($horarios)) return 'Consultar pelo WhatsApp';
        return collect($horarios)->map(fn ($h, $k) => "{$k}: {$h}")->join(' | ');
    }

    private function formatarSlots(array $slots): string
    {
        if (empty($slots)) return 'Nenhum horário disponível nos próximos 7 dias.';

        $linhas = [];
        foreach ($slots as $profissionalId => $diasSlots) {
            foreach ($diasSlots as $data => $horariosDisponiveis) {
                if (! empty($horariosDisponiveis)) {
                    $linhas[] = "Profissional #{$profissionalId} — {$data}: " . implode(', ', $horariosDisponiveis);
                }
            }
        }

        return implode("\n", $linhas) ?: 'Nenhum horário disponível.';
    }
}
