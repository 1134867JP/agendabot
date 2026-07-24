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
        {--stale : Rotaciona apenas tenants que ainda não passaram pela rotação segura}
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
        if ($this->option('stale')) {
            $query->whereNull('webhook_token_rotated_at');
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

            if (! $evolution->configurarWebhook($tenant->evolution_instance, $url, $novoToken)) {
                $this->error("#{$tenant->id} falhou; token anterior preservado.");
                $falhas++;

                continue;
            }

            try {
                $tenant->forceFill([
                    'webhook_token' => $novoToken,
                    'webhook_token_rotated_at' => now(),
                ])->save();
            } catch (\Throwable $e) {
                if ($tokenAnterior) {
                    $evolution->configurarWebhook($tenant->evolution_instance, $url, $tokenAnterior);
                }
                report($e);
                $this->error("#{$tenant->id} falhou ao persistir; configuração anterior restaurada.");
                $falhas++;

                continue;
            }

            $this->info("#{$tenant->id} rotacionado.");
        }

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
