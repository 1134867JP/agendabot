<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Models\Agendamento;
use App\Models\OperationalEvent;
use App\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentConfirmationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RegistraFalha, SerializesModels;

    public function handle(EvolutionApiService $evolution): void
    {
        Agendamento::with('tenant')
            ->whereIn('status', ['agendado', 'confirmado'])
            ->whereNull('confirmation_sent_at')
            ->whereBetween('data_hora', [now()->addHours(36), now()->addHours(48)])
            ->each(function (Agendamento $agendamento) use ($evolution): void {
                $data = $agendamento->data_hora->format('d/m \à\s H:i');
                $message = "Olá, {$agendamento->cliente_nome}! Confirma seu agendamento em {$data}? Responda SIM para confirmar ou NÃO para cancelar.";
                $sent = $evolution->enviarMensagem($agendamento->tenant->evolution_instance, $agendamento->cliente_telefone, $message);
                if ($sent) {
                    $agendamento->update(['confirmation_sent_at' => now()]);
                } else {
                    OperationalEvent::record($agendamento->tenant_id, 'integration_failure', [
                        'provider' => 'evolution', 'metadata' => ['operation' => 'appointment_confirmation'],
                    ]);
                }
            });
    }

    public function failed(\Throwable $e): void
    {
        $this->registrarFalha($e, null, ['evento' => 'enviar_confirmacoes_agendamento']);
    }
}
