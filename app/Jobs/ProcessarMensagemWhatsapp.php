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
    public int $timeout = 45;

    public function __construct(
        private Tenant $tenant,
        private string $telefone,
        private string $mensagem,
        private ?string $evolutionMessageId = null,
        private ?string $pushName = null,
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

        // 2. Buscar ou criar cliente — usa pushName do WhatsApp se disponível
        $nomeInicial = $this->pushName ?? 'Cliente WhatsApp';
        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone' => $this->telefone],
            ['nome' => $nomeInicial],
        );

        // Atualizar nome se veio como placeholder e agora temos o pushName real
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

        // 4. Se aguardando/em atendimento humano → apenas salva mensagem e não processa com Claude
        if (in_array($conversa->status_v2, ['aguardando_humano', 'em_atendimento_humano'])) {
            $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId);
            return;
        }

        // 5. Salvar mensagem do cliente
        $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId);

        // 6. Buscar histórico das últimas 6 mensagens para o Claude (economiza tokens)
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

        // 7. Buscar slots e agendamento pendente em paralelo
        $horariosDisponiveis = count($historico) >= 3
            ? $agendamentoService->buscarHorariosDisponiveis($this->tenant, 4)
            : [];

        $agendamentoPendente       = $this->buscarAgendamentoPendente($cliente);
        $agendamentoPendenteInfo   = $agendamentoPendente
            ? $this->formatarAgendamentoPendente($agendamentoPendente)
            : null;

        // 8. Chamar Claude
        $resultado = $claude->processar($this->tenant, $historico, $horariosDisponiveis, $agendamentoPendenteInfo);

        // Registrar uso de tokens para controle de custo
        if (! empty($resultado['usage'])) {
            TokenUsage::create(array_merge(
                ['tenant_id' => $this->tenant->id, 'model' => config('services.claude.model')],
                $resultado['usage'],
            ));
        }

        // 9. Processar ação retornada pelo Claude
        $agendamentoCriado = true;
        match ($resultado['acao']) {
            'agendar'    => $agendamentoCriado = $this->processarAgendamento($resultado['dados'], $cliente, $agendamentoService),
            'confirmar'  => $this->confirmarAgendamento($agendamentoPendente),
            'cancelar'   => $this->cancelarAgendamento($agendamentoPendente, $agendamentoService),
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

    private function formatarAgendamentoPendente(Agendamento $agendamento): string
    {
        $dataHora = Carbon::parse($agendamento->data_hora ?? $agendamento->inicio)
            ->locale('pt_BR')
            ->translatedFormat('d/m/Y \à\s H:i');

        $servico = $agendamento->profissional?->nome
            ?? $agendamento->recurso?->nome
            ?? 'serviço';

        return "📅 {$dataHora} — {$servico}";
    }

    private function confirmarAgendamento(?Agendamento $agendamento): void
    {
        if ($agendamento) {
            $agendamento->update(['status' => 'confirmado']);
        }
    }

    private function cancelarAgendamento(?Agendamento $agendamento, AgendamentoService $service): void
    {
        if ($agendamento) {
            $service->cancelar($agendamento);
        }
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
