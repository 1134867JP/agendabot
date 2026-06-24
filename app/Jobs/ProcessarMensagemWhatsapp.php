<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Services\AgendamentoService;
use App\Services\ClaudeAgentService;
use App\Services\EvolutionApiService;
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
    public int $timeout = 45;

    public function __construct(
        private Tenant $tenant,
        private string $telefone,
        private string $mensagem,
        private ?string $evolutionMessageId = null,
    ) {}

    public function handle(
        ClaudeAgentService $claude,
        AgendamentoService $agendamentoService,
        EvolutionApiService $evolution,
    ): void {
        // 1. Evitar duplicata por evolution_message_id
        if ($this->evolutionMessageId && Mensagem::where('evolution_message_id', $this->evolutionMessageId)->exists()) {
            return;
        }

        // 2. Buscar ou criar cliente
        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone' => $this->telefone],
            ['nome' => 'Cliente WhatsApp'],
        );

        // 3. Buscar ou criar conversa
        $conversa = Conversa::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone_cliente' => $this->telefone],
            ['status_v2' => 'ativa', 'cliente_id' => $cliente->id, 'etapa' => 'idle'],
        );

        if (! $conversa->cliente_id) {
            $conversa->update(['cliente_id' => $cliente->id]);
        }

        // 4. Se aguardando/em atendimento humano → apenas salva mensagem e não processa com Claude
        if (in_array($conversa->status_v2, ['aguardando_humano', 'em_atendimento_humano'])) {
            $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId);
            return;
        }

        // 5. Salvar mensagem do cliente
        $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId);

        // 6. Buscar histórico das últimas 10 mensagens para o Claude
        $historico = $conversa->mensagens()
            ->latest('enviada_em')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn ($m) => [
                'role'    => $m->remetente === 'cliente' ? 'user' : 'assistant',
                'content' => $m->conteudo,
            ])
            ->values()
            ->all();

        // 7. Buscar slots disponíveis nos próximos 4 dias
        $horariosDisponiveis = $agendamentoService->buscarHorariosDisponiveis($this->tenant, 4);

        // 8. Chamar Claude
        $resultado = $claude->processar($this->tenant, $historico, $horariosDisponiveis);

        // 9. Processar ação retornada pelo Claude
        $agendamentoCriado = true;
        match ($resultado['acao']) {
            'agendar'    => $agendamentoCriado = $this->processarAgendamento($resultado['dados'], $cliente, $agendamentoService),
            'transferir' => $this->transferirParaHumano($conversa),
            default      => null,
        };

        // Se o agendamento falhou, substituir resposta e transferir para humano
        if ($resultado['acao'] === 'agendar' && ! $agendamentoCriado) {
            $resultado['resposta'] = 'Desculpe, houve um problema técnico ao confirmar seu agendamento. Um atendente entrará em contato em breve. 🙏';
            $this->transferirParaHumano($conversa);
        }

        // 10. Salvar resposta do bot e enviar ao cliente
        $conversa->registrarMensagem('bot', $resultado['resposta']);
        $evolution->enviarMensagem($this->tenant->evolution_instance, $this->telefone, $resultado['resposta']);
    }

    private function processarAgendamento(array $dados, Cliente $cliente, AgendamentoService $service): bool
    {
        try {
            // Atualizar nome do cliente se identificado (e não for o placeholder padrão)
            if (! empty($dados['cliente_nome']) && $dados['cliente_nome'] !== 'Cliente WhatsApp') {
                $cliente->update(['nome' => $dados['cliente_nome']]);
            }

            $service->criarAgendamentoV2($this->tenant, array_merge($dados, [
                'cliente_id' => $cliente->id,
                'origem'     => 'bot',
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::error('ProcessarMensagemWhatsapp: falha ao criar agendamento', [
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
                'dados'  => $dados,
                'tenant' => $this->tenant->id,
            ]);

            return false;
        }
    }

    private function transferirParaHumano(Conversa $conversa): void
    {
        $conversa->update(['status_v2' => 'aguardando_humano']);
    }
}
