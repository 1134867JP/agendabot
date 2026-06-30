<?php

namespace App\Console\Commands;

use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ForceSincronizar extends Command
{
    protected $signature   = 'whatsapp:sync {tenant_slug} {--clear-lock}';
    protected $description = 'Força sincronização de conversas WhatsApp, liberando lock se necessário';

    public function handle(): void
    {
        $tenant = Tenant::where('slug', $this->argument('tenant_slug'))->firstOrFail();
        $lockKey = "sync_whatsapp_tenant_{$tenant->id}";

        if ($this->option('clear-lock') || Cache::has($lockKey)) {
            Cache::forget($lockKey);
            $this->warn("Lock liberado para {$tenant->slug}");
        }

        Cache::put($lockKey, true, now()->addMinutes(10));
        SincronizarConversasWhatsappJob::dispatch($tenant)->onQueue('default');

        $this->info("Sync iniciado para {$tenant->nome}. Acompanhe: tail -f storage/logs/laravel.log | grep SYNC");
    }
}
