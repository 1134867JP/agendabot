<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Models\Agendamento;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Agendamento que já terminou e ninguém marcou fica "agendado" para sempre e
 * distorce relatórios e o histórico do cliente. Depois de uma folga, consideramos
 * o atendimento realizado; a equipe registra a falta pelo painel quando for o caso.
 */
class ConcluirAgendamentosPassadosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RegistraFalha, SerializesModels;

    /** Tempo após o fim para a equipe marcar falta antes da conclusão automática. */
    public const HORAS_DE_FOLGA = 3;

    public int $tries = 1;

    public int $timeout = 60;

    public function handle(): void
    {
        $limite = now()->subHours(self::HORAS_DE_FOLGA);

        $concluidos = Agendamento::whereIn('status', ['agendado', 'confirmado'])
            ->where('fim', '<', $limite)
            ->update(['status' => 'concluido', 'updated_at' => now()]);

        if ($concluidos > 0) {
            Log::channel('jobs')->info('AGENDAMENTOS_CONCLUIDOS_AUTOMATICAMENTE', ['quantidade' => $concluidos]);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->registrarFalha($e, null, ['evento' => 'concluir_agendamentos_passados']);
    }
}
