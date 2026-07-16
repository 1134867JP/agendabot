<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\ConversaSyncService;
use App\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SincronizarConversasWhatsappJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;
    public int $uniqueFor = 900;

    private const LIMITE_CHATS_SYNC = 30;

    public function __construct(private readonly Tenant $tenant) {}

    public function uniqueId(): string
    {
        return (string) $this->tenant->id;
    }

    public function handle(EvolutionApiService $evolution, ConversaSyncService $sync): void
    {
        if (! $this->tenant->evolution_instance) {
            $this->atualizarStatus([
                'status' => 'failed',
                'message' => 'WhatsApp não configurado.',
            ], 5);
            return;
        }

        $instance = $this->tenant->evolution_instance;
        $this->atualizarStatus([
            'status' => 'running',
            'processed' => 0,
            'total' => 0,
            'imported' => 0,
            'ignored' => 0,
            'errors' => 0,
            'message' => 'Buscando conversas recentes no WhatsApp.',
            'started_at' => now()->toIso8601String(),
        ]);

        Log::info('SYNC_START', ['tenant' => $this->tenant->slug]);

        $nomesPorTelefone = $sync->buildNomesMap($evolution->fetchContacts($instance));
        $chatsTotal = $evolution->fetchChats($instance);
        $chats = $sync->chatsRecentesLimitados($chatsTotal, self::LIMITE_CHATS_SYNC);

        $totalChats = count($chats);
        $importados = 0;
        $erros = 0;
        $semMensagem = 0;
        $ignorados = 0;

        $this->atualizarStatus([
            'status' => 'running',
            'total' => $totalChats,
            'message' => $totalChats > 0
                ? 'Importando somente conversas com mensagens.'
                : 'Nenhuma conversa recente encontrada.',
        ]);

        foreach ($chats as $index => $chat) {
            try {
                $result = $sync->processarChat(
                    $this->tenant,
                    $evolution,
                    $instance,
                    $chat,
                    $nomesPorTelefone,
                );

                $importados += $result['importados'];
                $semMensagem += $result['sem_mensagem'] ? 1 : 0;
                $ignorados += ($result['ignorado'] ?? false) ? 1 : 0;
            } catch (\Throwable $e) {
                $erros++;
                Log::warning('SYNC_CHAT_ERRO', [
                    'tenant' => $this->tenant->slug,
                    'jid' => data_get($chat, 'remoteJid') ?? data_get($chat, 'id') ?? '?',
                    'erro' => $e->getMessage(),
                ]);
            }

            $this->atualizarStatus([
                'status' => 'running',
                'processed' => $index + 1,
                'total' => $totalChats,
                'imported' => $importados,
                'ignored' => $ignorados + $semMensagem,
                'errors' => $erros,
                'message' => 'Organizando conversas e contatos.',
            ]);
        }

        $removidos = $sync->limparRegistrosVazios($this->tenant);

        Log::info('SYNC_DONE', [
            'tenant' => $this->tenant->slug,
            'total_chats_api' => count($chatsTotal),
            'chats_sync' => $totalChats,
            'importados' => $importados,
            'sem_mensagem' => $semMensagem,
            'ignorados' => $ignorados,
            'erros' => $erros,
            'nomes_map' => count($nomesPorTelefone),
            'conversas_vazias_removidas' => $removidos['conversas'],
            'clientes_vazios_removidos' => $removidos['clientes'],
        ]);

        $this->atualizarStatus([
            'status' => 'completed',
            'processed' => $totalChats,
            'total' => $totalChats,
            'imported' => $importados,
            'ignored' => $ignorados + $semMensagem,
            'errors' => $erros,
            'removed' => $removidos['conversas'],
            'message' => $erros > 0
                ? 'Sincronização concluída com algumas falhas.'
                : 'Conversas atualizadas e organizadas.',
            'finished_at' => now()->toIso8601String(),
        ], 5);
    }

    public function failed(\Throwable $e): void
    {
        $this->atualizarStatus([
            'status' => 'failed',
            'message' => 'Não foi possível concluir a sincronização. Tente novamente.',
            'error' => app()->environment('production') ? null : $e->getMessage(),
            'finished_at' => now()->toIso8601String(),
        ], 5);

        Log::error('SYNC_FALHOU', [
            'tenant' => $this->tenant->slug,
            'erro' => $e->getMessage(),
        ]);
    }

    private function atualizarStatus(array $dados, int $ttlMinutos = 15): void
    {
        $key = "sync_whatsapp_tenant_{$this->tenant->id}";
        $atual = Cache::get($key, []);

        if (! is_array($atual)) {
            $atual = [];
        }

        Cache::put(
            $key,
            array_merge($atual, $dados, ['updated_at' => now()->toIso8601String()]),
            now()->addMinutes($ttlMinutos),
        );
    }
}
