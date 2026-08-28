<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Models\Agendamento;
use App\Services\OutboundMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentConfirmationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RegistraFalha, SerializesModels;

    public function handle(OutboundMessageService $outboundMessages): void
    {
        Agendamento::with('tenant')
            ->whereIn('status', ['agendado', 'confirmado'])
            ->whereNull('confirmation_sent_at')
            ->whereBetween('data_hora', [now()->addHours(36), now()->addHours(48)])
            ->whereHas('tenant', fn ($query) => $query
                ->where('whatsapp_conectado', true)
                ->whereIn('subscription_status', ['trial', 'active']))
            ->each(function (Agendamento $agendamento) use ($outboundMessages): void {
                if (! $agendamento->cliente_telefone) {
                    return;
                }

                $data = $agendamento->data_hora->format('d/m \à\s H:i');
                $message = "Olá, {$agendamento->cliente_nome}! Confirma seu agendamento em {$data}? Responda SIM para confirmar ou NÃO para cancelar.";
                $outboundMessages->queue(
                    tenant: $agendamento->tenant,
                    telefone: $agendamento->cliente_telefone,
                    conteudo: $message,
                    purpose: 'appointment_confirmation',
                    idempotencyKey: "appointment-confirmation:{$agendamento->id}",
                    agendamento: $agendamento,
                );
            });
    }

    public function failed(\Throwable $e): void
    {
        $this->registrarFalha($e, null, ['evento' => 'enviar_confirmacoes_agendamento']);
    }
}
