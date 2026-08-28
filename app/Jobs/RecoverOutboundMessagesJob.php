<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Models\OutboundMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RecoverOutboundMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RegistraFalha, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function handle(): void
    {
        OutboundMessage::query()
            ->where('status', OutboundMessage::STATUS_PROCESSING)
            ->where('locked_at', '<', now()->subMinutes(2))
            ->update([
                'status' => OutboundMessage::STATUS_PENDING,
                'locked_at' => null,
            ]);

        // Uma segunda janela de tentativas recupera indisponibilidades mais
        // longas sem criar um ciclo infinito para falhas permanentes.
        OutboundMessage::query()
            ->where('status', OutboundMessage::STATUS_FAILED)
            ->where('attempts', '<', 10)
            ->where('failed_at', '<=', now()->subMinutes(15))
            ->update([
                'status' => OutboundMessage::STATUS_PENDING,
                'failed_at' => null,
            ]);

        OutboundMessage::query()
            ->where('status', OutboundMessage::STATUS_PENDING)
            ->where('created_at', '<=', now()->subMinute())
            ->orderBy('id')
            ->limit(500)
            ->pluck('id')
            ->each(fn (int $id) => SendOutboundMessageJob::dispatch($id)->onQueue('messages'));
    }

    public function failed(Throwable $e): void
    {
        $this->registrarFalha($e, null, ['evento' => 'recuperar_mensagens_saida']);
    }
}
