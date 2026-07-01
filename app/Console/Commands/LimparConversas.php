<?php

namespace App\Console\Commands;

use App\Models\Conversa;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimparConversas extends Command
{
    protected $signature   = 'conversas:limpar {tenant : Slug ou ID do tenant}';
    protected $description = 'Apaga todas as conversas (e mensagens) de um tenant, mantendo os clientes cadastrados';

    public function handle(): int
    {
        $identificador = $this->argument('tenant');

        $tenant = ctype_digit($identificador)
            ? Tenant::find($identificador)
            : Tenant::where('slug', $identificador)->first();

        if (!$tenant) {
            $this->error("Tenant não encontrado: {$identificador}");
            return self::FAILURE;
        }

        $totalConversas = Conversa::where('tenant_id', $tenant->id)->count();

        if ($totalConversas === 0) {
            $this->info("Nenhuma conversa para apagar em {$tenant->nome}.");
            return self::SUCCESS;
        }

        DB::transaction(function () use ($tenant) {
            Conversa::where('tenant_id', $tenant->id)->delete();
        });

        $this->info("{$totalConversas} conversa(s) apagada(s) de {$tenant->nome} (as mensagens foram removidas em cascata).");

        return self::SUCCESS;
    }
}
