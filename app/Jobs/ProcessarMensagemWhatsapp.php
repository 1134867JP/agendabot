<?php

namespace App\Jobs;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Models\TokenUsage;
use App\Services\AgendamentoService;
use App\Services\ClaudeAgentService;
use App\Services\EvolutionApiService;
use App\Services\IntencaoService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessarMensagemWhatsapp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];
    public int $timeout = 90;

    public function __construct(
        private Tenant $tenant,
        private string $telefone,
        private string $mensagem,
        private ?string $evolutionMessageId = null,
        private ?string $pushName = null,
        private string $tipo = 'texto',
    ) {}

    public function handle(
        ClaudeAgentService $claude,
        AgendamentoService $agendamentoService,
        EvolutionApiService $evolution,
        IntencaoService $intencao,
    ): void {
        // 1. Evitar duplicata por evolution_message_id
        if ($this->evolutionMessageId && Mensagem::where('evolution_message_id', $this->evolutionMessageId)->exists()) {
            return;
        }

        // 2. Buscar ou criar cliente — usa pushName do WhatsApp se disponível
        $nomeInicial = $this->pushName ?? 'Cliente WhatsApp';
        $cliente = $this->firstOrCreateSafe(
            Cliente::class,
            ['tenant_id' => $this->tenant->id, 'telefone' => $this->telefone],
            ['nome' => $nomeInicial],
        );

        if ($this->pushName && $cliente->nome === 'Cliente WhatsApp') {
            $cliente->update(['nome' => $this->pushName]);
        }

        // 3. Buscar ou criar conversa
        $conversa = $this->firstOrCreateSafe(
            Conversa::class,
            ['tenant_id' => $this->tenant->id, 'telefone_cliente' => $this->telefone],
            ['status_v2' => 'ativa', 'cliente_id' => $cliente->id],
        );

        if (! $conversa->cliente_id) {
            $conversa->update(['cliente_id' => $cliente->id]);
        }

        // 4. Se aguardando/em atendimento humano → apenas salva mensagem e não processa com Claude
        if (in_array($conversa->status_v2, ['aguardando_humano', 'em_atendimento_humano'])) {
            $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId, $this->tipo);
            return;
        }

        // 4b. Verificar limite de agendamentos via bot do plano (isentos não têm limite)
        $limiteBot = $this->tenant->isento_cobranca ? null : config("plans.{$this->tenant->plano}.limite_bot_mes");
        if ($limiteBot !== null) {
            $agendamentosMes = Agendamento::where('tenant_id', $this->tenant->id)
                ->where('origem', 'bot')
                ->where('status', '!=', 'cancelado')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $alertaKey = "alerta_limite_80_{$this->tenant->id}_" . now()->format('Ym');
            if ($agendamentosMes >= (int) ($limiteBot * 0.8) && ! cache()->has($alertaKey)) {
                cache()->put($alertaKey, true, now()->endOfMonth());
                $evolution->enviarMensagem(
                    $this->tenant->evolution_instance,
                    $this->tenant->telefone_whatsapp,
                    "⚠️ *AgendaBot — Aviso de limite*\n\nVocê atingiu 80% do limite de agendamentos via bot do seu plano ({$agendamentosMes}/{$limiteBot} este mês).\n\nFaça upgrade para continuar recebendo agendamentos automaticamente: " . url('/renovar'),
                );
            }

            if ($agendamentosMes >= $limiteBot) {
                $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId);
                $aviso = "Olá! 😕 Nosso sistema de agendamento automático está temporariamente pausado este mês.\nPor favor, entre em contato diretamente para agendar.";
                $conversa->registrarMensagem('bot', $aviso);
                $evolution->enviarMensagem($this->tenant->evolution_instance, $this->telefone, $aviso);
                return;
            }
        }

        // 5. Salvar mensagem do cliente (dedup atômico: se outro job concorrente já inseriu
        // essa evolution_message_id, a constraint unique dispara e tratamos como duplicata)
        try {
            DB::transaction(fn () => $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId, $this->tipo));
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
            Log::channel('jobs')->info('MENSAGEM_DUPLICADA_IGNORADA', [
                'tenant'               => $this->tenant->id,
                'telefone'             => $this->telefone,
                'evolution_message_id' => $this->evolutionMessageId,
            ]);
            return;
        }

        // 5b. Triagem automática por palavra-chave: transfere para humano sem gastar uma
        // chamada ao Claude quando o cliente pede atendente ou menciona um termo configurado.
        $triagem = $this->tenant->triagemConfig();
        if ($this->deveTransferirPorPalavraChave($triagem['palavras_chave_humano'])) {
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
                'role'    => $m->remetente === 'cliente' ? 'user' : 'assistant',
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
                $carry[array_key_last($carry)]['content'] .= "\n" . $msg['content'];
            } else {
                $carry[] = $msg;
            }
            return $carry;
        }, []));

        Log::channel('jobs')->debug('BOT_HISTORICO', [
            'tenant'     => $this->tenant->id,
            'telefone'   => $this->telefone,
            'total_msgs' => count($historico),
            'roles'      => array_column($historico, 'role'),
        ]);

        // 7. Buscar agendamento pendente
        $agendamentoPendente = $this->buscarAgendamentoPendente($cliente);

        // 8. Pré-filtro de intenções simples — evita chamada ao Claude para confirmações/cancelamentos óbvios
        $intencaoDetectada = $agendamentoPendente
            ? $intencao->detectarConfirmacao($this->mensagem)
            : null;

        if ($intencaoDetectada) {
            if ($intencaoDetectada === 'confirmar') {
                $agendamentoPendente->update(['status' => 'confirmado']);
                $resposta = 'Perfeito! Agendamento confirmado. ✅';
            } else {
                $agendamentoService->cancelar($agendamentoPendente);
                $resposta = 'Entendido, agendamento cancelado.';
            }
        } else {
            // 9. Chamar Claude com tool use
            $clienteInfo = ['id' => $cliente->id, 'nome' => $cliente->nome, 'telefone' => $cliente->telefone];
            $resultado   = $claude->processar($this->tenant, $historico, $clienteInfo, $agendamentoPendente);

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
            Log::channel('jobs')->error('EVOLUTION_SEND_FAILED', [
                'tenant'   => $this->tenant->id,
                'telefone' => $this->telefone,
                'resposta' => mb_substr($resposta, 0, 200),
            ]);
        }

        Log::channel('jobs')->info('BOT_RESPOSTA', ['telefone' => $this->telefone, 'resposta' => mb_substr($resposta, 0, 200)]);
    }

    /**
     * firstOrCreate não é atômico: sob concorrência (ex. sync retroativo e mensagem em
     * tempo real chegando juntos), duas execuções podem tentar inserir o mesmo registro
     * único ao mesmo tempo. Se isso acontecer, a segunda apenas busca o registro que a
     * primeira já criou, em vez de propagar a exceção de unique constraint.
     *
     * @template TModel of Model
     * @param class-string<TModel> $modelClass
     * @return TModel
     */
    private function firstOrCreateSafe(string $modelClass, array $unique, array $extra = []): Model
    {
        try {
            // DB::transaction usa SAVEPOINT quando já há uma transação em andamento, então uma
            // violação de unique constraint aqui não aborta uma transação externa mais ampla.
            return DB::transaction(fn () => $modelClass::firstOrCreate($unique, $extra));
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
            return $modelClass::where($unique)->firstOrFail();
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505' || str_contains($e->getMessage(), 'duplicate key value violates unique constraint');
    }

    private function deveTransferirPorPalavraChave(array $palavrasChave): bool
    {
        if (empty($palavrasChave)) {
            return false;
        }

        $mensagem = mb_strtolower($this->mensagem);

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
