<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class ReconfigureWebhooks extends Command
{
    protected $signature   = 'whatsapp:reconfigure-webhooks';
    protected $description = 'Reconfigura o webhook da Evolution API em todas as instâncias ativas para incluir CONNECTION_UPDATE';

    public function handle(EvolutionApiService $evolution): int
    {
        $tenants = Tenant::whereNotNull('evolution_instance')->where('ativo', true)->get();

        $this->info("Reconfigurando {$tenants->count()} instâncias...");

        foreach ($tenants as $tenant) {
            if (! $tenant->webhook_token) {
                $tenant->update(['webhook_token' => \Illuminate\Support\Str::random(32)]);
            }
            $webhookUrl = route('webhook', $tenant->slug);
            $ok = $evolution->configurarWebhook($tenant->evolution_instance, $webhookUrl, $tenant->webhook_token);

            $status = $ok ? '<info>OK</info>' : '<error>FALHOU</error>';
            $this->line("  {$tenant->slug} ({$tenant->evolution_instance}): {$status}");
        }

        $this->info('Concluído.');
        return self::SUCCESS;
    }
}
