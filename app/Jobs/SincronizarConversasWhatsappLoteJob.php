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

class SincronizarConversasWhatsappLoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly array $chats,
        private readonly int $offset,
        private readonly int $total,
        private readonly bool $ultimoLote,
    ) {}

    public function handle(EvolutionApiService $evolution, ConversaSyncService $sync): void
    {
        if (! $this->tenant->evolution_instance) {
            throw new \RuntimeException('WhatsApp não configurado durante a sincronização.');
        }

        $instance = $this->tenant->evolution_instance;
        $nomesPorTelefone = Cache::get($this->chaveNomes());

        if (! is_array($nomesPorTelefone)) {
            $nomesPorTelefone = $sync->buildNomesMap($evolution->fetchContacts($instance));
            Cache::put($this->chaveNomes(), $nomesPorTelefone, now()->addHour());
        }

        $status = Cache::get($this->chaveStatus(), []);
        $importados = (int) data_get($status, 'imported', 0);
        $ignorados = (int) data_get($status, 'ignored', 0);
        $erros = (int) data_get($status, 'errors', 0);

        foreach ($this->chats as $indice => $chat) {
            try {
                $result = $sync->processarChat(
                    $this->tenant,
                    $evolution,
                    $instance,
                    $chat,
                    $nomesPorTelefone,
                );

                $importados += (int) $result['importados'];
                if (($result['sem_mensagem'] ?? false) || ($result['ignorado'] ?? false)) {
                    $ignorados++;
                }
            } catch (\Throwable $e) {
                $erros++;
                Log::warning('SYNC_CHAT_ERRO', [
                    'tenant' => $this->tenant->slug,
                    'jid' => data_get($chat, 'remoteJid') ?? data_get($chat, 'id') ?? '?',
                    'erro' => $e->getMessage(),
                ]);
            }

            $processados = min($this->offset + $indice + 1, $this->total);
            $this->atualizarStatus([
                'status' => 'running',
                'processed' => $processados,
                'total' => $this->total,
                'imported' => $importados,
                'ignored' => $ignorados,
                'errors' => $erros,
                'message' => "Sincronizando {$processados} de {$this->total} conversas.",
            ]);
        }

        Log::info('SYNC_BATCH_DONE', [
            'tenant' => $this->tenant->slug,
            'offset' => $this->offset,
            'quantidade' => count($this->chats),
            'total' => $this->total,
        ]);

        if (! $this->ultimoLote) {
            return;
        }

        $removidos = $sync->limparRegistrosVazios($this->tenant);
        Cache::forget($this->chaveNomes());

        Log::info('SYNC_DONE', [
            'tenant' => $this->tenant->slug,
            'total_chats_api' => $this->total,
            'importados' => $importados,
            'ignorados' => $ignorados,
            'erros' => $erros,
            'nomes_map' => count($nomesPorTelefone),
            'conversas_vazias_removidas' => $removidos['conversas'],
            'clientes_vazios_removidos' => $removidos['clientes'],
        ]);

        $this->atualizarStatus([
            'status' => 'completed',
            'processed' => $this->total,
            'total' => $this->total,
            'imported' => $importados,
            'ignored' => $ignorados,
            'errors' => $erros,
            'removed' => $removidos['conversas'],
            'message' => $erros > 0
                ? 'Sincronização concluída com algumas falhas.'
                : 'Todas as conversas foram atualizadas.',
            'finished_at' => now()->toIso8601String(),
        ], 5);
    }

    public function failed(\Throwable $e): void
    {
        Cache::forget($this->chaveNomes());

        $this->atualizarStatus([
            'status' => 'failed',
            'message' => 'A sincronização foi interrompida em um dos lotes. Tente novamente.',
            'error' => app()->environment('production') ? null : $e->getMessage(),
            'finished_at' => now()->toIso8601String(),
        ], 5);

        Log::error('SYNC_BATCH_FAILED', [
            'tenant' => $this->tenant->slug,
            'offset' => $this->offset,
            'erro' => $e->getMessage(),
        ]);
    }

    private function chaveStatus(): string
    {
        return "sync_whatsapp_tenant_{$this->tenant->id}";
    }

    private function chaveNomes(): string
    {
        return "sync_whatsapp_nomes_tenant_{$this->tenant->id}";
    }

    private function atualizarStatus(array $dados, int $ttlMinutos = 15): void
    {
        $atual = Cache::get($this->chaveStatus(), []);

        if (! is_array($atual)) {
            $atual = [];
        }

        Cache::put(
            $this->chaveStatus(),
            array_merge($atual, $dados, ['updated_at' => now()->toIso8601String()]),
            now()->addMinutes($ttlMinutos),
        );
    }
}
