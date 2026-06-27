<?php

namespace App\Providers;

use App\Services\AgendamentoService;
use App\Services\ClaudeAgentService;
use App\Services\EvolutionApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
