<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DiagnosticarEvolutionApi extends Command
{
    protected $signature = 'evolution:diagnosticar {tenant_slug}';

    protected $description = 'Mostra dados brutos da Evolution API para diagnóstico do sync';

    public function handle(EvolutionApiService $evolution): void
    {
        $tenant = Tenant::where('slug', $this->argument('tenant_slug'))->firstOrFail();
        $instance = $tenant->evolution_instance;
        $baseUrl = config('services.evolution.url');
        $apiKey = config('services.evolution.key');

        $this->info("=== TENANT: {$tenant->nome} | INSTANCE: {$instance} ===\n");

        // ── 1. Contacts ──────────────────────────────────────────────────────
        $this->info('--- findContacts (primeiros 3) ---');
        $contacts = $evolution->fetchContacts($instance);
        $this->line('Total retornado: '.count($contacts));
        foreach (array_slice($contacts, 0, 3) as $c) {
            $this->line(json_encode($c));
        }

        // ── 2. Chats ─────────────────────────────────────────────────────────
        $this->info("\n--- findChats (primeiros 5 individuais) ---");
        $chats = $evolution->fetchChats($instance);
        $this->line('Total retornado: '.count($chats));
        $individuais = collect($chats)->filter(function ($c) {
            $jid = data_get($c, 'remoteJid') ?? data_get($c, 'id') ?? '';

            return ! str_contains($jid, '@g.us') && ! str_contains($jid, '@newsletter');
        })->values();
        $this->line('Individuais: '.$individuais->count());
        foreach ($individuais->take(5) as $chat) {
            $jid = data_get($chat, 'remoteJid') ?? data_get($chat, 'id') ?? '?';
            $this->line("  JID: {$jid}");
            $this->line('  Keys: '.implode(', ', array_keys($chat)));
        }

        // ── 3. Messages de um chat ────────────────────────────────────────────
        $primeiro = $individuais->first();
        if (! $primeiro) {
            $this->warn('Nenhum chat individual encontrado.');

            return;
        }

        $remoteJid = data_get($primeiro, 'remoteJid') ?? data_get($primeiro, 'id');
        $this->info("\n--- findMessages para: {$remoteJid} ---");

        foreach ([
            'where.key.remoteJid' => ['where' => ['key' => ['remoteJid' => $remoteJid]], 'limit' => 5],
            'where.remoteJid' => ['where' => ['remoteJid' => $remoteJid], 'limit' => 5],
        ] as $label => $body) {
            $res = Http::withHeaders(['apikey' => $apiKey])
                ->timeout(15)
                ->post("{$baseUrl}/chat/findMessages/{$instance}", $body);

            $this->line("Formato [{$label}] → HTTP {$res->status()}");
            $json = $res->json();

            if (! is_array($json)) {
                $this->line('  Resposta: '.substr($res->body(), 0, 300));

                continue;
            }

            $this->line('  Keys raiz: '.implode(', ', array_keys($json)));

            $records = $json['messages']['records']
                ?? (is_array($json['messages'] ?? null) ? $json['messages'] : null)
                ?? ($json['records'] ?? null)
                ?? (isset($json[0]) ? $json : null)
                ?? [];

            $this->line('  Records encontrados: '.count($records));
            if (! empty($records[0])) {
                $this->line('  Primeiro record keys: '.implode(', ', array_keys($records[0])));
                $this->line('  pushName: '.(data_get($records[0], 'pushName') ?? '(vazio)'));
                $this->line('  messageType: '.(data_get($records[0], 'messageType') ?? '(vazio)'));
            }
        }
    }
}
