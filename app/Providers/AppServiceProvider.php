<?php

namespace App\Providers;

use App\Services\AgendamentoService;
use App\Services\ClaudeAgentService;
use App\Services\EvolutionApiService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClaudeAgentService::class);
        $this->app->singleton(EvolutionApiService::class);
        $this->app->singleton(AgendamentoService::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Rate limit do webhook da Evolution API por tenant (não por IP):
        // todos os eventos chegam do mesmo IP (container único da Evolution),
        // então chavear por IP faria todos os tenants dividirem um único bucket.
        RateLimiter::for('evolution-webhook', fn (Request $request) => Limit::perMinute(240)->by('evolution-webhook:'.$request->route('tenantSlug')));

        // Log de queries DML (INSERT/UPDATE/DELETE) para auditoria
        if (config('app.db_query_log', false)) {
            DB::listen(function ($query) {
                $sql = strtoupper(ltrim($query->sql));
                if (! str_starts_with($sql, 'INSERT') &&
                    ! str_starts_with($sql, 'UPDATE') &&
                    ! str_starts_with($sql, 'DELETE')) {
                    return;
                }

                Log::channel('db')->info('DB_DML', [
                    'sql'      => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms'  => $query->time,
                ]);
            });
        }
    }
}
