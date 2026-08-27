<?php

namespace App\Console\Commands;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Services\AgendamentoService;
use App\Services\ClaudeAgentService;
use App\Services\ConversaSyncService;
use App\Services\EvolutionApiService;
use App\Services\IntencaoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BotSimular extends Command
{
    protected $signature = 'bot:simular {tenant? : Slug do tenant} {--telefone=5551999999999 : Telefone do cliente simulado} {--reset : Apaga histórico da conversa antes de iniciar}';

    protected $description = 'Simula uma conversa com o bot sem gastar tokens reais (HTTP fakado)';

    public function handle(): int
    {
        $slug = $this->argument('tenant') ?? $this->pedirTenant();
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            $this->error("Tenant '{$slug}' não encontrado.");

            return 1;
        }

        $telefone = $this->option('telefone');

        if ($this->option('reset')) {
            Conversa::where('tenant_id', $tenant->id)
                ->where('telefone_cliente', $telefone)
                ->delete();
            $this->line('<fg=yellow>Histórico resetado.</fg=yellow>');
        }

        $this->line('');
        $this->line("🤖 <fg=cyan>Agendou Simulator</fg=cyan> — tenant: <fg=green>{$tenant->nome}</fg=green>");
        $this->line("   Telefone simulado: <fg=yellow>{$telefone}</fg=yellow>");
        $this->line('   Digite <fg=red>sair</fg=red> para encerrar | <fg=yellow>reset</fg=yellow> para nova conversa');
        $this->line(str_repeat('─', 60));

        $msgId = 1;

        while (true) {
            $mensagem = $this->ask('<fg=cyan>Você</fg=cyan>');

            if (in_array(strtolower(trim($mensagem ?? '')), ['sair', 'exit', 'quit'])) {
                $this->line("\n<fg=yellow>Encerrando simulação.</fg=yellow>");
                break;
            }

            if (strtolower(trim($mensagem ?? '')) === 'reset') {
                Conversa::where('tenant_id', $tenant->id)
                    ->where('telefone_cliente', $telefone)
                    ->delete();
                $this->line('<fg=yellow>Conversa resetada.</fg=yellow>');
                $msgId = 1;

                continue;
            }

            // Interceptar chamadas HTTP para não gastar tokens
            $respostaBot = null;

            Http::fake([
                // Anthropic API → resposta simulada baseada na mensagem
                'api.anthropic.com/*' => function ($request) use ($mensagem, &$respostaBot) {
                    $resposta = $this->gerarRespostaFake($request->data(), $mensagem);
                    $respostaBot = $resposta['resposta'];
                    $json = json_encode($resposta);

                    return Http::response([
                        'content' => [['type' => 'text', 'text' => $json]],
                        'usage' => [
                            'input_tokens' => 0,
                            'output_tokens' => 0,
                            'cache_creation_input_tokens' => 0,
                            'cache_read_input_tokens' => 0,
                        ],
                    ], 200);
                },
                // Evolution API → silenciar envio real
                '*' => Http::response(['status' => 'ok'], 200),
            ]);

            try {
                // Persistir a mensagem como o webhook faria em produção
                $mensagemModel = app(ConversaSyncService::class)->registrarMensagemRecebida(
                    $tenant,
                    $telefone,
                    $mensagem,
                    'sim_'.$msgId++,
                    'Cliente Simulado',
                );

                if (! $mensagemModel) {
                    $this->line('<fg=yellow>Mensagem duplicada ignorada.</fg=yellow>');

                    continue;
                }

                $job = new ProcessarMensagemWhatsapp(
                    tenant: $tenant,
                    telefone: $telefone,
                    mensagemId: $mensagemModel->id,
                );

                // Rodar síncronamente (sem queue)
                app()->call([$job, 'handle'], [
                    'claude' => app(ClaudeAgentService::class),
                    'agendamentoService' => app(AgendamentoService::class),
                    'evolution' => app(EvolutionApiService::class),
                    'intencao' => app(IntencaoService::class),
                ]);
            } catch (\Throwable $e) {
                $this->error('Erro no job: '.$e->getMessage());

                continue;
            }

            // Buscar última mensagem do bot salva no banco
            $conversa = Conversa::where('tenant_id', $tenant->id)
                ->where('telefone_cliente', $telefone)
                ->first();

            $ultimaMensagem = $conversa?->mensagens()
                ->where('remetente', 'bot')
                ->latest('enviada_em')
                ->first();

            $textoBot = $ultimaMensagem?->conteudo ?? $respostaBot ?? '(sem resposta)';

            $this->line('');
            $this->line('<fg=green>🤖 Bot</fg=green>  '.str_replace("\n", "\n       ", $textoBot));
            $this->line('');
        }

        return 0;
    }

    private function pedirTenant(): string
    {
        $tenants = Tenant::where('ativo', true)->pluck('slug', 'nome')->toArray();

        if (empty($tenants)) {
            $this->error('Nenhum tenant ativo encontrado. Rode os seeders primeiro.');
            exit(1);
        }

        $escolha = $this->choice('Qual tenant?', array_keys($tenants));

        return $tenants[$escolha];
    }

    /**
     * Gera uma resposta fake da Claude baseada no contexto da mensagem,
     * simulando o fluxo de agendamento sem chamar a API real.
     */
    private function gerarRespostaFake(array $requestData, string $ultimaMensagem): array
    {
        $systemPrompt = collect($requestData['system'] ?? [])->pluck('text')->join(' ');
        $mensagens = $requestData['messages'] ?? [];
        $totalMensagens = count($mensagens);
        $msg = mb_strtolower(trim($ultimaMensagem));

        // Detectar contexto pelo histórico e pelo system prompt
        $temPendente = str_contains($systemPrompt, 'PENDENTE:');
        $temSlots = str_contains($systemPrompt, 'SLOTS:');

        // Fluxo simplificado de simulação
        if ($totalMensagens <= 1) {
            return $this->acao('duvida', 'Olá! 😊 Seja bem-vindo(a)! Como posso te ajudar? Quer fazer um agendamento?');
        }

        if ($temPendente && preg_match('/^(sim|ok|confirmar?|isso|pode|certo)$/u', $msg)) {
            return $this->acao('confirmar', 'Perfeito! ✅ Agendamento confirmado com sucesso!');
        }

        if ($temPendente && preg_match('/^(n[aã]o?|cancelar?|desistir?)$/u', $msg)) {
            return $this->acao('cancelar', 'Entendido, agendamento cancelado. Posso te ajudar com algo mais?');
        }

        if (preg_match('/quero|agendar|marcar|reservar|sim|quero agendar/u', $msg)) {
            return $this->acao('duvida', 'Ótimo! 📋 Com qual profissional você quer agendar? Digite o nome ou número da opção.');
        }

        if ($temSlots && preg_match('/\d{1,2}[:h]\d{0,2}|\d{1,2}h/u', $msg)) {
            // Mensagem tem horário e há slots — simular agendamento
            return $this->acao('agendar', 'Agendamento marcado! ✅ Vejo você em breve.', [
                'cliente_nome' => 'Cliente Simulado',
                'profissional_id' => 1,
                'servico_id' => 1,
                'data' => now()->addDay()->format('Y-m-d'),
                'horario' => '09:00',
            ]);
        }

        if (preg_match('/\d{1,2}\/\d{1,2}|\bamanh[aã]\b|\bhoje\b|\bsegunda\b|\bterca\b|\bquarta\b|\bquinta\b|\bsexta\b/u', $msg)) {
            return $this->acao('duvida', "📅 Horários disponíveis:\n09:00 · 09:30 · 10:00 · 10:30 · 14:00 · 14:30\n\nQual prefere?");
        }

        if (preg_match('/[a-záéíóú]{3,}/u', $msg) && $totalMensagens >= 2) {
            return $this->acao('duvida', 'Para qual data? Pode ser amanhã, uma data específica (ex: 28/06) ou dia da semana. 📆');
        }

        return $this->acao('duvida', 'Desculpe, não entendi bem. Pode repetir de outro jeito? 😊');
    }

    private function acao(string $acao, string $resposta, array $extras = []): array
    {
        return array_merge(['acao' => $acao, 'resposta' => $resposta], $extras);
    }
}
