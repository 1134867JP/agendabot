<?php

namespace App\Jobs;

use App\Exceptions\EvolutionApiException;
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
        if (! $evolution->configurado()) {
            throw EvolutionApiException::configuracaoAusente();
        }

        $evolution->criarInstancia($this->tenant->evolution_instance);

        $webhookUrl = route('webhook', $this->tenant->slug);
        $webhookConfigurado = $evolution->configurarWebhook(
            $this->tenant->evolution_instance,
            $webhookUrl,
            $this->tenant->webhook_token,
        );

        if (! $webhookConfigurado) {
            throw EvolutionApiException::webhookNaoConfigurado();
        }

        $this->tenant->update(['ativo' => true]);
    }

    public function failed(\Throwable $e): void
    {
        $this->registrarFalha($e, $this->tenant->id, ['provider' => 'evolution', 'evento' => 'criar_instancia']);
    }
}
