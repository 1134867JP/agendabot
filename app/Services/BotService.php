<?php

namespace App\Services;

use App\Jobs\NotificarDonoCancelamentoJob;
use App\Models\Agendamento;
use App\Models\Conversa;
use App\Models\Recurso;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BotService
{
    public function __construct(
        private ClaudeService $claude,
        private AgendamentoService $agendamento,
        private EvolutionApiService $evolution,
    ) {}

    public function processarWebhook(Tenant $tenant, string $telefone, string $mensagem): void
    {
        if (in_array($tenant->subscription_status, ['blocked', 'canceled'])) {
            return;
        }

        // Buscar ou criar conversa antes de qualquer detecção de intenção
        $conversa = Conversa::firstOrCreate(
            ['tenant_id' => $tenant->id, 'telefone_cliente' => $telefone],
            ['etapa' => 'idle', 'historico_mensagens' => []],
        );

        // ── Confirmar ou cancelar após pergunta de confirmação de cancelamento ──
        if ($conversa->etapa === 'aguardando_confirmacao_cancelamento') {
            $this->processarRespostaCancelamento($tenant, $telefone, $mensagem, $conversa);
            return;
        }

        // ── Detectar intenção de cancelamento ────────────────────────────────
        if ($this->isCancelamento($mensagem)) {
            $this->processarCancelamento($tenant, $telefone, $conversa);
            return;
        }

        // ── Detectar confirmação de lembrete D-1 ─────────────────────────────
        if ($this->isConfirmacao($mensagem)) {
            $this->processarConfirmacao($tenant, $telefone);
            return;
        }

        // ── Fluxo normal (Claude) ─────────────────────────────────────────────
        if ($conversa->etapa === 'escolhendo_horario'
            && isset($conversa->contexto['recurso_id'], $conversa->contexto['data'])
        ) {
            $this->injetarSlotsNoContexto($conversa);
        }

        $resultado = $this->claude->processarMensagem($tenant, $conversa, $mensagem);

        $conversa->adicionarMensagem('user', $mensagem);
        $conversa->adicionarMensagem('assistant', $resultado['resposta']);
        $conversa->etapa = $resultado['proxima_etapa'];

        $contexto = $conversa->contexto ?? [];
        foreach ($resultado['dados_extraidos'] as $key => $value) {
            if ($value !== null) {
                $contexto[$key] = $value;
            }
        }
        $conversa->contexto = $contexto;
        $conversa->save();

        if ($resultado['proxima_etapa'] === 'concluido') {
            $this->finalizarAgendamento($conversa, $tenant);
        }

        $this->evolution->enviarMensagem(
            $tenant->evolution_instance,
            $telefone,
            $resultado['resposta'],
        );
    }

    // ── Detecção de intenção ──────────────────────────────────────────────────

    private function isCancelamento(string $mensagem): bool
    {
        $texto    = mb_strtolower(trim($mensagem));
        $palavras = [
            'cancelar', 'cancela', 'cancelo', 'quero cancelar',
            'cancela meu horario', 'cancelar horário', '❌ cancelar',
            'não vou poder', 'nao vou poder',
        ];

        foreach ($palavras as $palavra) {
            if (str_contains($texto, $palavra)) {
                return true;
            }
        }

        return false;
    }

    private function isConfirmacao(string $mensagem): bool
    {
        $texto    = mb_strtolower(trim($mensagem));
        $palavras = [
            'confirmo', 'confirmar', '✅ confirmo', 'confirmo sim',
            'vou sim', 'estarei lá', 'estarei la', 'tá bom', 'ta bom',
        ];

        foreach ($palavras as $palavra) {
            if ($texto === $palavra || str_contains($texto, $palavra)) {
                return true;
            }
        }

        return false;
    }

    // ── Cancelamento em 2 passos ──────────────────────────────────────────────

    private function processarCancelamento(Tenant $tenant, string $telefone, Conversa $conversa): void
    {
        $agendamento = Agendamento::where('tenant_id', $tenant->id)
            ->where('cliente_telefone', $telefone)
            ->where('status', 'confirmado')
            ->where('inicio', '>', now())
            ->orderBy('inicio')
            ->first();

        if (! $agendamento) {
            $this->evolution->enviarMensagem(
                $tenant->evolution_instance,
                $telefone,
                "Não encontrei nenhum agendamento futuro para cancelar. 😊\n"
                . "Se precisar de algo, é só falar!",
            );
            return;
        }

        $data    = Carbon::parse($agendamento->inicio)->locale('pt_BR');
        $horario = $data->format('H:i');
        $recurso = $agendamento->recurso->nome;

        $conversa->update([
            'etapa'    => 'aguardando_confirmacao_cancelamento',
            'contexto' => ['agendamento_id' => $agendamento->id],
        ]);

        $this->evolution->enviarMensagem(
            $tenant->evolution_instance,
            $telefone,
            "Encontrei seu agendamento:\n\n"
            . "📅 {$data->translatedFormat('l, d \d\e F')} às {$horario}\n"
            . "📍 {$recurso}\n\n"
            . "Tem certeza que deseja cancelar?\n"
            . "Responda *SIM* para confirmar o cancelamento\n"
            . "ou *NÃO* para manter o horário.",
        );
    }

    private function processarRespostaCancelamento(
        Tenant $tenant,
        string $telefone,
        string $mensagem,
        Conversa $conversa,
    ): void {
        $texto = mb_strtolower(trim($mensagem));

        if (in_array($texto, ['sim', 's', 'sim, cancelar', 'confirmo', 'pode cancelar'])) {
            $agendamentoId = $conversa->contexto['agendamento_id'] ?? null;
            $agendamento   = Agendamento::with(['recurso', 'tenant'])->find($agendamentoId);

            if ($agendamento) {
                $agendamento->update(['status' => 'cancelado']);

                $this->evolution->enviarMensagem(
                    $tenant->evolution_instance,
                    $telefone,
                    "✅ Agendamento cancelado com sucesso.\n\nSe quiser remarcar, é só me dizer! 😊",
                );

                NotificarDonoCancelamentoJob::dispatch($agendamento);
            } else {
                $this->evolution->enviarMensagem(
                    $tenant->evolution_instance,
                    $telefone,
                    "Não consegui encontrar o agendamento. Por favor, entre em contato diretamente.",
                );
            }

            $conversa->update(['etapa' => 'idle', 'contexto' => null]);
            return;
        }

        if (in_array($texto, ['não', 'nao', 'n', 'manter', 'não cancelar'])) {
            $conversa->update(['etapa' => 'idle', 'contexto' => null]);

            $this->evolution->enviarMensagem(
                $tenant->evolution_instance,
                $telefone,
                "Tudo certo! Seu horário está mantido. 👍",
            );
            return;
        }

        // Resposta não reconhecida — pedir de novo
        $this->evolution->enviarMensagem(
            $tenant->evolution_instance,
            $telefone,
            "Desculpe, não entendi. Responda *SIM* para cancelar ou *NÃO* para manter o horário.",
        );
    }

    private function processarConfirmacao(Tenant $tenant, string $telefone): void
    {
        $agendamento = Agendamento::where('tenant_id', $tenant->id)
            ->where('cliente_telefone', $telefone)
            ->where('status', 'confirmado')
            ->where('inicio', '>', now())
            ->orderBy('inicio')
            ->first();

        if (! $agendamento) {
            $this->evolution->enviarMensagem(
                $tenant->evolution_instance,
                $telefone,
                "Obrigado! 😊 Se precisar de algo, é só falar.",
            );
            return;
        }

        $data = Carbon::parse($agendamento->inicio)->locale('pt_BR');

        $this->evolution->enviarMensagem(
            $tenant->evolution_instance,
            $telefone,
            "Ótimo, confirmado! ✅\n\nTe esperamos {$data->translatedFormat('l')} às {$data->format('H:i')}. Até lá! 👋",
        );
    }

    // ── Helpers do fluxo normal ───────────────────────────────────────────────

    private function injetarSlotsNoContexto(Conversa $conversa): void
    {
        $recurso = Recurso::find($conversa->contexto['recurso_id']);
        if (! $recurso) {
            return;
        }

        $data        = Carbon::parse($conversa->contexto['data']);
        $slots       = $recurso->slotsDisponiveis($data);
        $disponiveis = collect($slots)->where('disponivel', true)->pluck('hora')->join(', ');

        $contexto                    = $conversa->contexto;
        $contexto['slots_disponiveis'] = $disponiveis;
        $conversa->contexto          = $contexto;
        $conversa->save();
    }

    private function finalizarAgendamento(Conversa $conversa, Tenant $tenant): void
    {
        $ctx     = $conversa->contexto;
        $recurso = Recurso::find($ctx['recurso_id']);
        $inicio  = Carbon::parse("{$ctx['data']} {$ctx['horario']}");
        $fim     = $inicio->copy()->addMinutes($recurso->duracao_padrao_minutos);

        $this->agendamento->criar([
            'tenant_id'        => $tenant->id,
            'recurso_id'       => $ctx['recurso_id'],
            'cliente_nome'     => $ctx['nome_cliente'],
            'cliente_telefone' => $conversa->telefone_cliente,
            'inicio'           => $inicio,
            'fim'              => $fim,
            'valor_total'      => $recurso->valor_hora * ($recurso->duracao_padrao_minutos / 60),
        ]);

        $conversa->update(['etapa' => 'idle', 'contexto' => null]);
    }
}
