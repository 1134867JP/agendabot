<?php

namespace App\Console\Commands;

use App\Support\FailedJobsFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlertarJobsFalhadosCommand extends Command
{
    protected $signature = 'jobs:alertar-falhas';

    protected $description = 'Verifica se há jobs novos em failed_jobs desde a última checagem e dispara um alerta.';

    private const CACHE_KEY = 'jobs_falhados_ultimo_id_verificado';

    public function handle(): int
    {
        $ultimoId = (int) Cache::get(self::CACHE_KEY, 0);

        $novos = DB::table('failed_jobs')
            ->where('id', '>', $ultimoId)
            ->orderBy('id')
            ->get();

        if ($novos->isEmpty()) {
            return self::SUCCESS;
        }

        $resumo = $novos
            ->map(fn ($job) => FailedJobsFormatter::formatar($job))
            ->map(fn (array $f) => "#{$f['id']} {$f['job']} — {$f['error']}")
            ->implode("\n");

        $mensagem = '🚨 '.$novos->count().' job(s) falharam na fila:'."\n".$resumo;

        if (config('logging.channels.slack.url')) {
            Log::channel('slack')->critical($mensagem);
        } else {
            Log::channel('daily')->critical('JOBS_FALHADOS: '.$mensagem);
        }

        Cache::put(self::CACHE_KEY, (int) $novos->max('id'));

        $this->warn($mensagem);

        return self::SUCCESS;
    }
}
