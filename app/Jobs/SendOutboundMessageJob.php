<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Models\OperationalEvent;
use App\Models\OutboundMessage;
use App\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SendOutboundMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RegistraFalha, SerializesModels;

    public int $tries = 5;

    public array $backoff = [15, 60, 180, 600];

    public int $timeout = 30;

    public int $uniqueFor = 900;

    public function __construct(public int $outboundMessageId) {}

    public function uniqueId(): string
    {
        return (string) $this->outboundMessageId;
    }

    public function handle(EvolutionApiService $evolution): void
    {
        $outbound = DB::transaction(function (): ?OutboundMessage {
            $message = OutboundMessage::query()
                ->with('tenant')
                ->lockForUpdate()
                ->find($this->outboundMessageId);

            if (! $message || $message->status === OutboundMessage::STATUS_SENT) {
                return null;
            }

            if (
                $message->status === OutboundMessage::STATUS_PROCESSING
                && $message->locked_at?->isAfter(now()->subMinutes(2))
            ) {
                return null;
            }

            $message->update([
                'status' => OutboundMessage::STATUS_PROCESSING,
                'attempts' => $message->attempts + 1,
                'locked_at' => now(),
                'last_error' => null,
                'failed_at' => null,
            ]);

            return $message;
        });

        if (! $outbound) {
            return;
        }

        try {
            $sent = $evolution->enviarMensagem(
                $outbound->tenant->evolution_instance,
                $outbound->telefone,
                $outbound->conteudo,
            );

            if (! $sent) {
                throw new RuntimeException('A Evolution API não confirmou o envio da mensagem.');
            }

            DB::transaction(function () use ($outbound): void {
                $message = OutboundMessage::query()->lockForUpdate()->find($outbound->id);
                if (! $message || $message->status === OutboundMessage::STATUS_SENT) {
                    return;
                }

                $message->update([
                    'status' => OutboundMessage::STATUS_SENT,
                    'sent_at' => now(),
                    'locked_at' => null,
                    'last_error' => null,
                    'failed_at' => null,
                ]);

                if ($message->agendamento_id && $message->purpose === 'appointment_reminder') {
                    $message->agendamento()->update(['lembrete_enviado' => true]);
                }

                if ($message->agendamento_id && $message->purpose === 'appointment_confirmation') {
                    $message->agendamento()->update(['confirmation_sent_at' => now()]);
                }
            });

            OperationalEvent::record($outbound->tenant_id, 'outbound_message_sent', [
                'provider' => 'evolution',
                'metadata' => [
                    'purpose' => $outbound->purpose,
                    'outbound_message_id' => $outbound->id,
                    'attempts' => $outbound->attempts,
                ],
            ]);
        } catch (Throwable $e) {
            OutboundMessage::whereKey($outbound->id)->update([
                'status' => OutboundMessage::STATUS_PENDING,
                'locked_at' => null,
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            OperationalEvent::record($outbound->tenant_id, 'integration_failure', [
                'provider' => 'evolution',
                'metadata' => [
                    'message' => $e->getMessage(),
                    'operation' => 'send_message',
                    'purpose' => $outbound->purpose,
                    'outbound_message_id' => $outbound->id,
                ],
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $outbound = OutboundMessage::find($this->outboundMessageId);
        $outbound?->update([
            'status' => OutboundMessage::STATUS_FAILED,
            'locked_at' => null,
            'failed_at' => now(),
            'last_error' => mb_substr($e->getMessage(), 0, 2000),
        ]);

        $this->registrarFalha($e, $outbound?->tenant_id, [
            'evento' => 'enviar_mensagem_saida',
            'outbound_message_id' => $this->outboundMessageId,
        ]);
    }
}
