<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CorrigirNomesClientesCommand extends Command
{
    protected $signature = 'clientes:corrigir-nomes {--tenant=} {--dry-run} {--limit=500}';

    protected $description = 'Corrige clientes cujo nome ficou como "Você"/"You"/igual ao telefone, re-buscando o pushName real via Evolution API (ignorando mensagens fromMe)';

    private const NOMES_INVALIDOS = ['você', 'you', 'cliente whatsapp', ''];

    public function handle(EvolutionApiService $evolution): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $query = Cliente::query()->with('tenant');

        if ($slug = $this->option('tenant')) {
            $tenant = Tenant::where('slug', $slug)->first();
            if (! $tenant) {
                $this->error("Tenant '{$slug}' não encontrado.");

                return self::FAILURE;
            }
            $query->where('tenant_id', $tenant->id);
        }

        $query->where(function ($q) {
            $q->whereRaw('LOWER(TRIM(nome)) IN (?, ?, ?, ?)', self::NOMES_INVALIDOS)
                ->orWhereColumn('nome', 'telefone');
        });

        $clientes = $query->limit($limit)->get();

        $this->info("Encontrados {$clientes->count()} clientes com nome suspeito.".($dryRun ? ' (modo dry-run)' : ''));

        $corrigidos = 0;
        $semResultado = 0;
        $erros = 0;

        foreach ($clientes as $cliente) {
            $tenant = $cliente->tenant;

            if (! $tenant || ! $tenant->evolution_instance) {
                $semResultado++;

                continue;
            }

            try {
                $nomeCorrigido = $this->buscarNomeReal($evolution, $tenant->evolution_instance, $cliente->telefone);
            } catch (\Throwable $e) {
                $erros++;
                Log::channel('jobs')->error('CORRECAO_NOME_ERRO', [
                    'cliente_id' => $cliente->id,
                    'tenant' => $tenant->slug,
                    'erro' => $e->getMessage(),
                ]);

                continue;
            }

            if (! $nomeCorrigido) {
                $semResultado++;
                Log::channel('jobs')->info('CORRECAO_NOME_SEM_RESULTADO', [
                    'cliente_id' => $cliente->id,
                    'tenant' => $tenant->slug,
                    'telefone' => $cliente->telefone,
                ]);

                continue;
            }

            $this->line("Cliente #{$cliente->id} ({$cliente->telefone}) [{$tenant->slug}]: '{$cliente->nome}' -> '{$nomeCorrigido}'");

            if (! $dryRun) {
                $cliente->update(['nome' => $nomeCorrigido]);
            }

            $corrigidos++;
        }

        $this->newLine();
        $this->info(
            "Total: {$clientes->count()} | Corrigidos: {$corrigidos} | Sem resultado: {$semResultado} | Erros: {$erros}"
            .($dryRun ? ' (dry-run, nada foi salvo)' : '')
        );

        return self::SUCCESS;
    }

    private function buscarNomeReal(EvolutionApiService $evolution, string $instance, string $telefone): ?string
    {
        foreach ($evolution->fetchContacts($instance) as $contato) {
            $jid = data_get($contato, 'remoteJid') ?? data_get($contato, 'id') ?? data_get($contato, 'jid');
            if (! $jid) {
                continue;
            }

            $tel = preg_replace('/@.*$/', '', $jid);
            if ($tel !== $telefone) {
                continue;
            }

            $nome = data_get($contato, 'pushName') ?? data_get($contato, 'notify') ?? data_get($contato, 'name');
            if ($this->nomeValido($nome)) {
                return $nome;
            }
        }

        $msgs = $evolution->fetchMessages($instance, "{$telefone}@s.whatsapp.net", 50);
        foreach ($msgs as $msg) {
            if (data_get($msg, 'key.fromMe')) {
                continue;
            }

            $pn = data_get($msg, 'pushName');
            if ($this->nomeValido($pn)) {
                return $pn;
            }
        }

        return null;
    }

    private function nomeValido(?string $nome): bool
    {
        return (bool) $nome && ! in_array(strtolower(trim($nome)), self::NOMES_INVALIDOS, true);
    }
}
