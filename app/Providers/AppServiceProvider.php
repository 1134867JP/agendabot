<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Services\AgendamentoService;
use App\Services\AgendouAgentService;
use App\Services\AgendouService;
use App\Services\AI\AiOrchestrator;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\CloudflareProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqGptOssProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\GroqQwenProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use App\Services\ClaudeAgentService;
use App\Services\EvolutionApiService;
use App\Support\RuntimeHealth;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiOrchestrator::class, fn ($app) => new AiOrchestrator([
            $app->make(ClaudeProvider::class),
            $app->make(GeminiProvider::class),
            $app->make(GroqQwenProvider::class),
            $app->make(GroqGptOssProvider::class),
            $app->make(GroqProvider::class),
            $app->make(CloudflareProvider::class),
            $app->make(OpenRouterProvider::class),
        ]));
        $this->app->singleton(AgendouAgentService::class);
        $this->app->singleton(ClaudeAgentService::class);
        $this->app->singleton(AgendouService::class);
        $this->app->singleton(EvolutionApiService::class);
        $this->app->singleton(AgendamentoService::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('evolution-webhook', fn (Request $request) => Limit::perMinute(240)
            ->by('evolution-webhook:'.$request->route('tenantSlug')));

        $this->registrarObservabilidadeDaFila();
        $this->registrarAuditoriaDml();
    }

    private function registrarObservabilidadeDaFila(): void
    {
        $inicios = [];
        $ultimosHeartbeats = [];

        Queue::looping(function (Looping $event) use (&$ultimosHeartbeats): void {
            $worker = (string) ($event->workerOptions?->name ?: 'default');
            $agora = now()->timestamp;
            $intervalo = (int) config('queue.monitoring.heartbeat_interval_seconds', 15);

            if ($agora - ($ultimosHeartbeats[$worker] ?? 0) < $intervalo) {
                return;
            }

            RuntimeHealth::touchWorker($worker);
            $ultimosHeartbeats[$worker] = $agora;
        });

        Queue::before(function (JobProcessing $event) use (&$inicios): void {
            $contexto = $this->contextoDoJob($event->connectionName, $event->job);
            $inicios[$contexto['uuid'] ?? $contexto['job_id'] ?? uniqid('job-', true)] = hrtime(true);
            Log::channel('jobs')->info('JOB_INICIADO', $contexto);
        });

        Queue::after(function (JobProcessed $event) use (&$inicios): void {
            $contexto = $this->contextoDoJob($event->connectionName, $event->job);
            $chave = $contexto['uuid'] ?? $contexto['job_id'] ?? null;
            $inicio = $chave ? ($inicios[$chave] ?? null) : null;
            $contexto['duration_ms'] = $inicio ? round((hrtime(true) - $inicio) / 1_000_000, 2) : null;
            if ($chave) {
                unset($inicios[$chave]);
            }
            Log::channel('jobs')->info('JOB_CONCLUIDO', $contexto);
        });

        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            Log::channel('jobs')->warning('JOB_TENTATIVA_FALHOU', array_merge(
                $this->contextoDoJob($event->connectionName, $event->job),
                ['exception' => get_class($event->exception).': '.$event->exception->getMessage()]
            ));
        });

        Queue::failing(function (JobFailed $event): void {
            Log::channel('jobs')->error('JOB_FALHOU_DEFINITIVAMENTE', array_merge(
                $this->contextoDoJob($event->connectionName, $event->job),
                ['exception' => get_class($event->exception).': '.$event->exception->getMessage()]
            ));
        });
    }

    private function contextoDoJob(string $connection, object $job): array
    {
        try {
            $payload = $job->payload();
            $command = (string) data_get($payload, 'data.command', '');
            $tenantId = null;

            foreach ([
                '/s:\\d+:"tenantId";i:(\\d+)/',
                '/s:\\d+:"tenant_id";i:(\\d+)/',
                '/App\\\\Models\\\\Tenant.*?s:2:"id";i:(\\d+)/s',
            ] as $pattern) {
                if (preg_match($pattern, $command, $match)) {
                    $tenantId = (int) $match[1];
                    break;
                }
            }

            return array_filter([
                'job' => class_basename((string) ($payload['displayName'] ?? $job->resolveName())),
                'connection' => $connection,
                'queue' => $job->getQueue(),
                'job_id' => $job->getJobId(),
                'uuid' => $payload['uuid'] ?? null,
                'attempt' => $job->attempts(),
                'tenant_id' => $tenantId,
            ], static fn ($value) => $value !== null && $value !== '');
        } catch (\Throwable $e) {
            return ['connection' => $connection, 'context_error' => $e->getMessage()];
        }
    }

    private function registrarAuditoriaDml(): void
    {
        // A auditoria é operacional e não deve interferir em testes que mockam a facade Log.
        if ($this->app->environment('testing') || ! config('app.db_query_log', true)) {
            return;
        }

        DB::listen(function ($query): void {
            $sql = ltrim($query->sql);
            if (! preg_match('/^(INSERT|UPDATE|DELETE)\\s+/i', $sql, $operation)) {
                return;
            }

            preg_match('/^(?:INSERT\\s+INTO|UPDATE|DELETE\\s+FROM)\\s+["`]?([a-zA-Z0-9_.]+)/i', $sql, $table);
            $tenant = app()->bound('tenant') ? app('tenant') : null;

            Log::channel('db')->info('DB_'.strtoupper($operation[1]), array_filter([
                'table' => isset($table[1]) ? trim($table[1], '"`') : null,
                'sql' => $query->sql,
                'binding_count' => count($query->bindings),
                'time_ms' => $query->time,
                'connection' => $query->connectionName,
                'tenant_id' => $tenant instanceof Tenant ? $tenant->id : null,
                'user_id' => auth()->id(),
                'route' => request()->route()?->getName(),
                'ip' => request()->ip(),
            ], static fn ($value) => $value !== null));
        });
    }
}
