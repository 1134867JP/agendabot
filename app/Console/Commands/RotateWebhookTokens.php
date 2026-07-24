<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RotateWebhookTokens extends Command
{
    protected $signature = 'security:rotate-webhook-tokens
        {--tenant=* : IDs dos tenants que serão rotacionados}
        {--dry-run : Apenas lista os tenants afetados}
        {--force : Executa sem confirmação interativa}';

    protected $description = 'Rotaciona tokens e reconfigura os webhooks da Evolution sem expor segredos na URL';

    public function handle(EvolutionApiService $evolution): int
    {
        $query = Tenant::query()->whereNotNull('evolution_instance')->orderBy('id');
        $tenantIds = array_values(array_filter(array_map('intval', $this->option('tenant'))));

        if ($tenantIds !== []) {
            $query->whereKey($tenantIds);
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant elegível foi encontrado.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $tenants->each(fn (Tenant $tenant) => $this->line("#{$tenant->id} {$tenant->slug}"));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Rotacionar {$tenants->count()} token(s) agora?")) {
            return self::SUCCESS;
        }

        $falhas = 0;

        foreach ($tenants as $tenant) {
            $tokenAnterior = $tenant->webhook_token;
            $novoToken = Str::random(64);
            $url = route('webhook', $tenant->slug);

            $tenant->forceFill(['webhook_token' => $novoToken])->save();

            if (! $evolution->configurarWebhook($tenant->evolution_instance, $url, $novoToken)) {
                $tenant->forceFill(['webhook_token' => $tokenAnterior])->save();
                $evolution->configurarWebhook($tenant->evolution_instance, $url, $tokenAnterior);
                $this->error("#{$tenant->id} falhou; token anterior restaurado.");
                $falhas++;

                continue;
            }

            $this->info("#{$tenant->id} rotacionado.");
        }

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
