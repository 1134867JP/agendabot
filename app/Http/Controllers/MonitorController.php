<?php

namespace App\Http\Controllers;

use App\Models\OperationalEvent;
use App\Support\ErroLogScanner;
use App\Support\FailedJobsFormatter;
use App\Support\RuntimeHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MonitorController extends Controller
{
    /**
     * Endpoint somente leitura, protegido por token (MONITOR_API_TOKEN), usado para
     * monitoramento externo do estado de erros da aplicação: jobs falhados, falhas
     * estruturadas de jobs/integrações e volume de exceções PHP nas últimas 24h.
     */
    public function erros(): JsonResponse
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(30)
            ->get()
            ->map(fn ($job) => FailedJobsFormatter::formatar($job))
            ->toArray();

        $falhasEstruturadas = OperationalEvent::with('tenant:id,nome')
            ->whereIn('type', ['job_failure', 'integration_failure'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (OperationalEvent $evento) => [
                'id' => $evento->id,
                'tipo' => $evento->type,
                'provider' => $evento->provider,
                'tenant' => $evento->tenant?->nome,
                'mensagem' => data_get($evento->metadata, 'message') ?? data_get($evento->metadata, 'evento'),
                'metadata' => $evento->metadata,
                'ocorrido_em' => $evento->created_at,
            ])
            ->toArray();

        return response()->json([
            'gerado_em' => now()->toIso8601String(),
            'failed_jobs_total' => DB::table('failed_jobs')->count(),
            'jobs_pendentes' => DB::table('jobs')->count(),
            'erros_php_24h' => ErroLogScanner::contarUltimas24h(),
            'runtime' => RuntimeHealth::status(),
            'failed_jobs' => $failedJobs,
            'falhas_estruturadas' => $falhasEstruturadas,
        ]);
    }
}
