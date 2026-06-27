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
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessarMensagemWhatsapp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];
    public int $timeout = 90; // tool-use loop needs more time

    public function __construct(
        private Tenant $tenant,
        private string $telefone,
        private string $mensagem,
        private ?string $evolutionMessageId = null,
        private ?string $pushName = null,
    ) {}

    public function handle(
        ClaudeAgentService $claude,
        EvolutionApiService $evolution,
    ): void {
        // 1. Evitar duplicata por evolution_message_id
        if ($this->evolutionMessageId && Mensagem::where('evolution_message_id', $this->evolutionMessageId)->exists()) {
            return;
        }

        // 2. Buscar ou criar cliente
        $nomeInicial = $this->pushName ?? 'Cliente WhatsApp';
        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone' => $this->telefone],
            ['nome' => $nomeInicial],
        );

        if ($this->pushName && $cliente->nome === 'Cliente WhatsApp') {
            $cliente->update(['nome' => $this->pushName]);
        }

        // 3. Buscar ou criar conversa
        $conversa = Conversa::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone_cliente' => $this->telefone],
            ['status_v2' => 'ativa', 'cliente_id' => $cliente->id],
        );

        if (! $conversa->cliente_id) {
            $conversa->update(['cliente_id' => $cliente->id]);
        }

        // 4. Se em atendimento humano → apenas salva e não processa com Claude
        if (in_array($conversa->status_v2, ['aguardando_humano', 'em_atendimento_humano'])) {
            $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId);
            return;
        }

        // 5. Salvar mensagem do cliente
        $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId);

        // 6. Buscar histórico das últimas 6 mensagens
        $historico = $conversa->mensagens()
            ->latest('enviada_em')
            ->limit(6)
            ->get()
            ->reverse()
            ->map(fn ($m) => [
                'role'    => $m->remetente === 'cliente' ? 'user' : 'assistant',
                'content' => $m->conteudo,
            ])
            ->values()
            ->all();

        // 7. Buscar agendamento pendente para contexto do agente
        $agendamentoPendente = $this->buscarAgendamentoPendente($cliente);

        // 8. Chamar o agente Claude (loop com tool use)
        $resultado = $claude->processar(
            $this->tenant,
            $historico,
            ['id' => $cliente->id, 'nome' => $cliente->nome, 'telefone' => $cliente->telefone],
            $agendamentoPendente,
        );

        // Registrar uso de tokens
        if (! empty($resultado['usage'])) {
            TokenUsage::create(array_merge(
                ['tenant_id' => $this->tenant->id, 'model' => config('services.claude.model')],
                $resultado['usage'],
            ));
        }

        // 9. Transferir para humano se o agente solicitou
        if ($resultado['transferir']) {
            $conversa->update(['status_v2' => 'aguardando_humano']);
        }

        // 10. Salvar resposta do bot e enviar ao cliente
        $conversa->registrarMensagem('bot', $resultado['resposta']);
        $evolution->enviarMensagem($this->tenant->evolution_instance, $this->telefone, $resultado['resposta']);
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
