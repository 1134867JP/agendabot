<?php

namespace App\Jobs;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\OperationalEvent;
use App\Models\Tenant;
use App\Models\TokenUsage;
use App\Services\AgendamentoService;
use App\Services\ClaudeAgentService;
use App\Services\EvolutionApiService;
use App\Services\IntencaoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processa uma mensagem de cliente JÁ PERSISTIDA pelo webhook (ver
 * ConversaSyncService::registrarMensagemRecebida) e responde via bot.
 *
 * O job pode ser despachado com delay (config bot.debounce_seconds): se o cliente
 * enviar várias mensagens curtas em sequência, apenas o job da mensagem mais recente
 * chama o Claude — os anteriores detectam que existe mensagem mais nova e retornam.
 * O histórico já mescla mensagens 'user' consecutivas num único turno, então a
 * resposta considera todas as mensagens acumuladas.
 */
class ProcessarMensagemWhatsapp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public int $timeout = 90;

    public function __construct(
        private Tenant $tenant,
        private string $telefone,
        private int $mensagemId,
    ) {}

    public function handle(
        ClaudeAgentService $claude,
        AgendamentoService $agendamentoService,
        EvolutionApiService $evolution,
        IntencaoService $intencao,
    ): void {
        $startedAt = hrtime(true);

        // 1. Carregar a mensagem persistida pelo webhook; se não existe mais, nada a fazer
        $mensagem = Mensagem::find($this->mensagemId);
        if (! $mensagem) {
            return;
        }

        $conversa = Conversa::find($mensagem->conversa_id);
        if (! $conversa) {
            return;
        }

        $conteudo = $mensagem->conteudo;

        // 2. Debounce: se já chegou mensagem MAIS NOVA do cliente nesta conversa, este job
        // não responde — o job da mensagem mais recente responderá com o histórico completo.
        if ($this->existeMensagemPosterior($conversa, $mensagem, 'cliente')) {
            Log::channel('jobs')->info('MENSAGEM_DEBOUNCED', [
                'tenant' => $this->tenant->id,
                'mensagem_id' => $mensagem->id,
            ]);

            return;
        }

        // 3. Idempotência em retry: se o bot já respondeu depois desta mensagem
        // (tentativa anterior que falhou após salvar a resposta), não responder de novo.
        if ($this->existeMensagemPosterior($conversa, $mensagem, 'bot')) {
            return;
        }

        // 4. Cliente (o webhook garante cliente_id; fallback defensivo para conversas antigas)
        $cliente = $conversa->cliente_id ? Cliente::find($conversa->cliente_id) : null;
        if (! $cliente) {
            $cliente = Cliente::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'telefone' => $this->telefone],
                ['nome' => 'Cliente WhatsApp'],
            );
            $conversa->update(['cliente_id' => $cliente->id]);
        }

        // 5. Se aguardando/em atendimento humano → mensagem já foi salva no webhook;
        // o bot não responde.
        if (in_array($conversa->status_v2, ['aguardando_humano', 'em_atendimento_humano'])) {
            return;
        }

        // 5b. Verificar limite de agendamentos via bot do plano (isentos não têm limite).
        // A mensagem do cliente já foi salva no webhook; aqui só respondemos o aviso.
        $limiteBot = $this->tenant->isento_cobranca ? null : config("plans.{$this->tenant->plano}.limite_bot_mes");
        if ($limiteBot !== null) {
            $agendamentosMes = Agendamento::where('tenant_id', $this->tenant->id)
                ->where('origem', 'bot')
                ->where('status', '!=', 'cancelado')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $alertaKey = "alerta_limite_80_{$this->tenant->id}_".now()->format('Ym');
            if ($agendamentosMes >= (int) ($limiteBot * 0.8) && ! cache()->has($alertaKey)) {
                cache()->put($alertaKey, true, now()->endOfMonth());
                $evolution->enviarMensagem(
                    $this->tenant->evolution_instance,
                    $this->tenant->telefone_whatsapp,
                    "⚠️ *Agendou — Aviso de limite*\n\nVocê atingiu 80% do limite de agendamentos via bot do seu plano ({$agendamentosMes}/{$limiteBot} este mês).\n\nFaça upgrade para continuar recebendo agendamentos automaticamente: ".url('/renovar'),
                );
            }

            if ($agendamentosMes >= $limiteBot) {
                $aviso = "Olá! 😕 Nosso sistema de agendamento automático está temporariamente pausado este mês.\nPor favor, entre em contato diretamente para agendar.";
                $conversa->registrarMensagem('bot', $aviso);
                $evolution->enviarMensagem($this->tenant->evolution_instance, $this->telefone, $aviso);

                return;
            }
        }

        // 5c. Triagem automática por palavra-chave: transfere para humano sem gastar uma
        // chamada ao Claude quando o cliente pede atendente ou menciona um termo configurado.
        $triagem = $this->tenant->triagemConfig();
        if ($this->deveTransferirPorPalavraChave($conteudo, $triagem['palavras_chave_humano'])) {
            $conversa->update(['status_v2' => 'aguardando_humano']);
            $mensagemTransferencia = $triagem['mensagem_transferencia']
                ?: 'Já vou te transferir para um atendente, um momento! 🙋';
            $conversa->registrarMensagem('bot', $mensagemTransferencia);
            $evolution->enviarMensagem($this->tenant->evolution_instance, $this->telefone, $mensagemTransferencia);

            return;
        }

        // 6. Buscar histórico das últimas 12 mensagens para o Claude manter contexto da conversa
        $historico = $conversa->mensagens()
            ->latest('enviada_em')
            ->latest('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->map(fn ($m) => [
                'role' => $m->remetente === 'cliente' ? 'user' : 'assistant',
                'content' => $m->conteudo,
            ])
            ->values()
            ->all();

        // A API do Claude exige que a primeira mensagem tenha role "user" (HTTP 400 caso contrário).
        // A janela das últimas 4 mensagens pode começar com uma resposta do bot (assistant),
        // então removemos as mensagens "assistant" iniciais até que o histórico comece com "user".
        while (! empty($historico) && $historico[0]['role'] !== 'user') {
            array_shift($historico);
        }

        // Mesclar mensagens consecutivas com o mesmo role — Claude rejeita sequências duplicadas.
        $historico = array_values(array_reduce($historico, function (array $carry, array $msg): array {
            if (! empty($carry) && end($carry)['role'] === $msg['role']) {
                $carry[array_key_last($carry)]['content'] .= "\n".$msg['content'];
            } else {
                $carry[] = $msg;
            }

            return $carry;
        }, []));

        Log::channel('jobs')->debug('BOT_HISTORICO', [
            'tenant' => $this->tenant->id,
            'total_msgs' => count($historico),
            'roles' => array_column($historico, 'role'),
        ]);

        // 7. Buscar agendamento pendente
        $agendamentoPendente = $this->buscarAgendamentoPendente($cliente);

        // 8. Pré-filtro de intenções simples — evita chamada ao Claude para confirmações/cancelamentos óbvios
        $intencaoDetectada = $agendamentoPendente
            ? $intencao->detectarConfirmacao($conteudo)
            : null;

        if ($intencaoDetectada) {
            if ($intencaoDetectada === 'confirmar') {
                $agendamentoPendente->update(['status' => 'confirmado', 'confirmed_by_client_at' => now()]);
                $resposta = 'Perfeito! Agendamento confirmado. ✅';
            } else {
                $agendamentoService->cancelar($agendamentoPendente);
                $resposta = 'Entendido, agendamento cancelado.';
            }
        } else {
            // 9. Chamar Claude com tool use
            $clienteInfo = ['id' => $cliente->id, 'nome' => $cliente->nome, 'telefone' => $cliente->telefone];
            $resultado = $claude->processar($this->tenant, $historico, $clienteInfo, $agendamentoPendente);

            if (! empty($resultado['usage'])) {
                TokenUsage::create(array_merge(
                    ['tenant_id' => $this->tenant->id, 'model' => config('services.claude.model')],
                    $resultado['usage'],
                ));
            }

            if ($resultado['transferir']) {
                $conversa->update(['status_v2' => 'aguardando_humano']);
            }

            $resposta = $resultado['resposta'];
        }

        // 10. Salvar resposta do bot e enviar ao cliente
        $conversa->registrarMensagem('bot', $resposta);

        $enviado = false;
        for ($tentativa = 1; $tentativa <= 3 && ! $enviado; $tentativa++) {
            $enviado = $evolution->enviarMensagem($this->tenant->evolution_instance, $this->telefone, $resposta);
            if (! $enviado && $tentativa < 3) {
                sleep(2 ** ($tentativa - 1));
            }
        }

        if (! $enviado) {
            OperationalEvent::record($this->tenant->id, 'integration_failure', [
                'provider' => 'evolution',
                'metadata' => ['operation' => 'send_message'],
            ]);
            Log::channel('jobs')->error('EVOLUTION_SEND_FAILED', [
                'tenant' => $this->tenant->id,
                'response_length' => mb_strlen($resposta),
            ]);
        }

        OperationalEvent::record($this->tenant->id, 'bot_response', [
            'provider' => 'evolution',
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'metadata' => ['sent' => $enviado],
        ]);

        Log::channel('jobs')->info('BOT_RESPONSE_SENT', ['tenant_id' => $this->tenant->id, 'response_length' => mb_strlen($resposta)]);
    }

    /**
     * Existe mensagem do remetente informado registrada DEPOIS da mensagem deste job?
     * Ordenação por (enviada_em, id) — o id desempata mensagens gravadas no mesmo segundo.
     */
    private function existeMensagemPosterior(Conversa $conversa, Mensagem $mensagem, string $remetente): bool
    {
        return $conversa->mensagens()
            ->where('remetente', $remetente)
            ->where(function (Builder $q) use ($mensagem) {
                $q->where('enviada_em', '>', $mensagem->enviada_em)
                    ->orWhere(function (Builder $q2) use ($mensagem) {
                        $q2->where('enviada_em', $mensagem->enviada_em)
                            ->where('id', '>', $mensagem->id);
                    });
            })
            ->exists();
    }

    private function deveTransferirPorPalavraChave(string $conteudo, array $palavrasChave): bool
    {
        if (empty($palavrasChave)) {
            return false;
        }

        $mensagem = mb_strtolower($conteudo);

        foreach ($palavrasChave as $palavra) {
            $palavra = mb_strtolower(trim((string) $palavra));
            if ($palavra !== '' && str_contains($mensagem, $palavra)) {
                return true;
            }
        }

        return false;
    }

    private function buscarAgendamentoPendente(Cliente $cliente): ?Agendamento
    {
        return Agendamento::where('tenant_id', $this->tenant->id)
            ->where('status', 'agendado')
            ->where(function ($q) use ($cliente) {
                $q->where('cliente_id', $cliente->id)
                    ->orWhere('cliente_telefone', $this->telefone);
            })
            ->where(function ($q) {
                $q->where(fn ($q2) => $q2->whereNotNull('data_hora')->where('data_hora', '>', now()))
                    ->orWhere(fn ($q2) => $q2->whereNull('data_hora')->where('inicio', '>', now()));
            })
            ->orderByRaw('COALESCE(data_hora, inicio)')
            ->first();
    }
}
