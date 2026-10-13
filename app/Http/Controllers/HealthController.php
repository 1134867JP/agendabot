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
        $mail = $this->mailCheck();
        $ready = $applicationHealthy && $runtime['ready'] && $mail !== 'not_configured';

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => array_merge($checks, ['mail' => $mail, 'runtime' => $runtime]),
            'timestamp' => now()->toIso8601String(),
        ], $ready ? 200 : 503);
    }

    /**
     * Sem um mailer real em produção, redefinição de senha e confirmação de
     * cadastro nunca chegam ao usuário (o padrão "log" só grava no arquivo).
     */
    private function mailCheck(): string
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return app()->isProduction() ? 'not_configured' : 'log_only';
        }

        return filled(config('mail.from.address')) ? 'ok' : 'not_configured';
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
