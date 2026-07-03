<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\ConversaSyncService;
use App\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SincronizarConversasWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 600;

    private const LIMITE_CHATS_SYNC = 30;

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(EvolutionApiService $evolution, ConversaSyncService $sync): void
    {
        if (!$this->tenant->evolution_instance) {
            Cache::forget("sync_whatsapp_tenant_{$this->tenant->id}");
            return;
        }

        $instance = $this->tenant->evolution_instance;

        Log::info('SYNC_START', ['tenant' => $this->tenant->slug]);

        // ── 1. Mapa nome→telefone via findContacts ───────────────────────────
        $nomesPorTelefone = $sync->buildNomesMap($evolution->fetchContacts($instance));

        // ── 2. Chats mais recentes, limitados para não sincronizar o histórico inteiro ──
        $chatsTotal = $evolution->fetchChats($instance);
        $chats      = $sync->chatsRecentesLimitados($chatsTotal, self::LIMITE_CHATS_SYNC);

        $totalChats  = count($chats);
        $importados  = 0;
        $erros       = 0;
        $semMensagem = 0;

        foreach ($chats as $chat) {
            try {
                $result = $sync->processarChat(
                    $this->tenant, $evolution, $instance, $chat, $nomesPorTelefone
                );
                $importados  += $result['importados'];
                $semMensagem += $result['sem_mensagem'] ? 1 : 0;
            } catch (\Throwable $e) {
                $erros++;
                Log::warning('SYNC_CHAT_ERRO', [
                    'tenant' => $this->tenant->slug,
                    'jid'    => data_get($chat, 'remoteJid') ?? data_get($chat, 'id') ?? '?',
                    'erro'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('SYNC_DONE', [
            'tenant'          => $this->tenant->slug,
            'total_chats_api' => count($chatsTotal),
            'chats_sync'      => $totalChats,
            'importados'      => $importados,
            'sem_mensagem'    => $semMensagem,
            'erros'           => $erros,
            'nomes_map'       => count($nomesPorTelefone),
        ]);

        Cache::forget("sync_whatsapp_tenant_{$this->tenant->id}");
    }

    public function failed(\Throwable $e): void
    {
        Cache::forget("sync_whatsapp_tenant_{$this->tenant->id}");
        Log::error('SYNC_FALHOU', [
            'tenant' => $this->tenant->slug,
            'erro'   => $e->getMessage(),
        ]);
    }
}
