<?php

namespace App\Jobs;

use App\Models\Conversa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpirarConversasInativasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function handle(): void
    {
        $expiradas = Conversa::where('status_v2', 'ativa')
            ->where('ultima_mensagem_em', '<', now()->subMinutes(30))
            ->update(['status_v2' => 'encerrada']);

        if ($expiradas > 0) {
            Log::info("ExpirarConversasInativasJob: {$expiradas} conversa(s) encerrada(s) por inatividade.");
        }
    }
}
