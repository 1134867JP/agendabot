<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Models\Conversa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpirarConversasInativasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RegistraFalha, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function handle(): void
    {
        $expiradas = Conversa::where('status_v2', 'ativa')
            ->where('ultima_mensagem_em', '<', now()->subMinutes(15))
            ->update(['status_v2' => 'encerrada']);

        if ($expiradas > 0) {
            Log::info("ExpirarConversasInativasJob: {$expiradas} conversa(s) encerrada(s) por inatividade.");
        }

        // Sem isso, uma resposta da equipe pelo celular deixaria o bot pausado
        // para sempre com aquele cliente.
        $retomarApos = (int) config('bot.retomar_apos_humano_minutos', 120);
        if ($retomarApos > 0) {
            $retomadas = Conversa::where('status_v2', 'em_atendimento_humano')
                ->where('ultima_mensagem_em', '<', now()->subMinutes($retomarApos))
                ->update(['status_v2' => 'encerrada']);

            if ($retomadas > 0) {
                Log::info("ExpirarConversasInativasJob: bot retomado em {$retomadas} conversa(s) após atendimento humano.");
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->registrarFalha($e, null, ['evento' => 'expirar_conversas_inativas']);
    }
}
