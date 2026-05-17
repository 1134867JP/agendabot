<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\BotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessarMensagemJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        private Tenant $tenant,
        private string $telefone,
        private string $mensagem,
    ) {}

    public function handle(BotService $bot): void
    {
        $bot->processarWebhook($this->tenant, $this->telefone, $this->mensagem);
    }
}
