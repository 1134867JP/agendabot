<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Models\Agendamento;
use App\Services\OutboundMessageService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnviarLembretesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RegistraFalha, SerializesModels;

    public function handle(OutboundMessageService $outboundMessages): void
    {
        $amanha = now()->addDay();

        $agendamentos = Agendamento::query()
            ->with(['recurso', 'profissional', 'tenant', 'cliente'])
            ->whereIn('status', ['agendado', 'confirmado'])
            ->where(fn ($q) => $q
                ->whereDate('inicio', $amanha->toDateString())
                ->orWhereDate('data_hora', $amanha->toDateString())
            )
            ->where('lembrete_enviado', false)
            ->whereHas('tenant', fn ($q) => $q->where('whatsapp_conectado', true)
                ->whereIn('subscription_status', ['trial', 'active'])
            )
            ->get();

        foreach ($agendamentos as $agendamento) {
            $cfg = $agendamento->tenant->configuracoes ?? [];
            if (($cfg['lembrete_ativo'] ?? true) === false) {
                continue;
            }

            $telefone = $agendamento->cliente_telefone
                ?? $agendamento->cliente?->telefone;

            if (! $telefone) {
                continue;
            }

            try {
                $mensagem = $this->montarMensagemLembrete($agendamento, $cfg['lembrete_texto'] ?? null);

                $outboundMessages->queue(
                    tenant: $agendamento->tenant,
                    telefone: $telefone,
                    conteudo: $mensagem,
                    purpose: 'appointment_reminder',
                    idempotencyKey: "appointment-reminder:{$agendamento->id}",
                    agendamento: $agendamento,
                );
            } catch (\Throwable $e) {
                Log::error("Falha ao enfileirar lembrete agendamento #{$agendamento->id}: {$e->getMessage()}");
            }
        }
    }

    private function montarMensagemLembrete(Agendamento $agendamento, ?string $textoPersonalizado): string
    {
        $dataHora = Carbon::parse($agendamento->data_hora ?? $agendamento->inicio)->locale('pt_BR');
        $horario = $dataHora->format('H:i');
        $nomeCliente = $agendamento->cliente_nome ?? $agendamento->cliente?->nome ?? 'Cliente';
        $recursoNome = $agendamento->recurso?->nome ?? $agendamento->profissional?->nome ?? '';
        $tenant = $agendamento->tenant->nome;

        $cabecalho = "👋 *Olá, {$nomeCliente}!*\n\n"
                   ."Lembrando que você tem um agendamento *amanhã*:\n\n"
                   ."📅 *Data:* {$dataHora->translatedFormat('l, d \d\e F')}\n"
                   ."⏰ *Horário:* {$horario}\n"
                   ."📍 *Local/Serviço:* {$recursoNome} — {$tenant}\n\n";

        $corpo = $textoPersonalizado
            ? trim($textoPersonalizado)."\n\n"
            : "Para *confirmar*, responda: ✅ *CONFIRMO*\n"
            ."Para *cancelar*, responda: ❌ *CANCELAR*\n\n";

        return $cabecalho.$corpo.'_Até amanhã!_';
    }

    public function failed(\Throwable $e): void
    {
        $this->registrarFalha($e, null, ['evento' => 'enviar_lembretes_d1']);
    }
}
