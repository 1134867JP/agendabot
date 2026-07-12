<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
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

        return response()->json([
            'status' => $healthy ? 'ok' : 'failed',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
