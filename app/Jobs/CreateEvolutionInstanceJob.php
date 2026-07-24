<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateEvolutionInstanceJob implements ShouldQueue
{
    use Queueable, RegistraFalha;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private Tenant $tenant) {}

    public function handle(EvolutionApiService $evolution): void
    {
        $evolution->criarInstancia($this->tenant->evolution_instance);

        $webhookUrl = route('webhook', $this->tenant->slug);
        $evolution->configurarWebhook(
            $this->tenant->evolution_instance,
            $webhookUrl,
            $this->tenant->webhook_token,
        );

        $this->tenant->update(['ativo' => true]);
    }

    public function failed(\Throwable $e): void
    {
        $this->registrarFalha($e, $this->tenant->id, ['provider' => 'evolution', 'evento' => 'criar_instancia']);
    }
}
