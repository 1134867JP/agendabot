<?php

namespace App\Http\Controllers;

use App\Support\RuntimeHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        [$checks, $healthy] = $this->applicationChecks();

        return response()->json([
            'status' => $healthy ? 'ok' : 'failed',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    public function ready(): JsonResponse
    {
        [$checks, $applicationHealthy] = $this->applicationChecks();
        $runtime = RuntimeHealth::status();
        $ready = $applicationHealthy && $runtime['ready'];

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => array_merge($checks, ['runtime' => $runtime]),
            'timestamp' => now()->toIso8601String(),
        ], $ready ? 200 : 503);
    }

    private function applicationChecks(): array
    {
        $checks = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'failed';
        }

        try {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
            $checks['queue'] = $failed > 10 ? 'degraded' : 'ok';
            $checks['failed_jobs_last_hour'] = $failed;
        } catch (\Throwable) {
            $checks['queue'] = 'failed';
        }

        $healthy = $checks['database'] === 'ok' && $checks['queue'] !== 'failed';

        return [$checks, $healthy];
    }
}
