<?php

namespace App\Providers;

use App\Services\AgendamentoService;
use App\Services\BotService;
use App\Services\ClaudeAgentService;
use App\Services\ClaudeService;
use App\Services\EvolutionApiService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClaudeService::class);
        $this->app->singleton(ClaudeAgentService::class);
        $this->app->singleton(EvolutionApiService::class);
        $this->app->singleton(BotService::class);
        $this->app->singleton(AgendamentoService::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
