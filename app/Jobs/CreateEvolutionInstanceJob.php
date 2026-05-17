<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateEvolutionInstanceJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(private Tenant $tenant) {}

    public function handle(EvolutionApiService $evolution): void
    {
        $evolution->criarInstancia($this->tenant->evolution_instance);

        $evolution->configurarWebhook(
            $this->tenant->evolution_instance,
            route('webhook', $this->tenant->slug),
        );

        $this->tenant->update(['ativo' => true]);
    }
}
