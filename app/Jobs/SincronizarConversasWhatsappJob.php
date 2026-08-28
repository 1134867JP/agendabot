<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\ConversaSyncService;
use App\Services\EvolutionApiService;
use App\Services\WhatsAppSyncState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SincronizarConversasWhatsappJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public int $timeout = 120;

    public int $uniqueFor = 900;

    private const TAMANHO_LOTE = 10;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $executionId,
    ) {}

    public function uniqueId(): string
    {
        return $this->tenant->id.':'.$this->executionId;
    }

    public function handle(
        EvolutionApiService $evolution,
        ConversaSyncService $sync,
        WhatsAppSyncState $syncState,
    ): void {
        if ($syncState->deveInterromper($this->tenant, $this->executionId)) {
            return;
        }

        $instance = $this->tenant->evolution_instance;
        $syncState->atualizar($this->tenant, $this->executionId, [
            'status' => 'running',
            'processed' => 0,
            'total' => 0,
            'imported' => 0,
            'ignored' => 0,
            'errors' => 0,
            'message' => 'Preparando todas as conversas do WhatsApp.',
            'started_at' => now()->toIso8601String(),
        ]);

        Log::info('SYNC_START', ['tenant' => $this->tenant->slug]);

        $nomesPorTelefone = $sync->buildNomesMap($evolution->fetchContacts($instance));
        if ($syncState->deveInterromper($this->tenant, $this->executionId)) {
            return;
        }
        Cache::put($syncState->chaveNomes($this->tenant), $nomesPorTelefone, now()->addHour());

        $chatsApi = $evolution->fetchChats($instance);
        if ($syncState->deveInterromper($this->tenant, $this->executionId)) {
            return;
        }
        $chats = $sync->chatsRecentesLimitados($chatsApi, count($chatsApi));
        $total = count($chats);

        $syncState->atualizar($this->tenant, $this->executionId, [
            'status' => 'running',
            'processed' => 0,
            'total' => $total,
            'message' => $total > 0
                ? "Sincronizando {$total} conversas em lotes seguros."
                : 'Nenhuma conversa encontrada.',
        ]);

        if ($total === 0) {
            $removidos = $sync->limparRegistrosVazios($this->tenant);
            Cache::forget($syncState->chaveNomes($this->tenant));

            $syncState->atualizar($this->tenant, $this->executionId, [
                'status' => 'completed',
                'processed' => 0,
                'total' => 0,
                'imported' => 0,
                'ignored' => 0,
                'errors' => 0,
                'removed' => $removidos['conversas'],
                'message' => 'Nenhuma conversa encontrada para sincronizar.',
                'finished_at' => now()->toIso8601String(),
            ], 5);

            return;
        }

        $jobs = [];
        foreach (array_chunk($chats, self::TAMANHO_LOTE) as $indice => $lote) {
            $offset = $indice * self::TAMANHO_LOTE;
            $jobs[] = new SincronizarConversasWhatsappLoteJob(
                $this->tenant,
                $lote,
                $offset,
                $total,
                $offset + count($lote) >= $total,
                $this->executionId,
            );
        }

        Bus::chain($jobs)
            ->onQueue($this->queue ?: 'sync')
            ->dispatch();
    }

    public function failed(\Throwable $e): void
    {
        $syncState = app(WhatsAppSyncState::class);
        Cache::forget($syncState->chaveNomes($this->tenant));

        $syncState->atualizar($this->tenant, $this->executionId, [
            'status' => 'failed',
            'message' => 'Não foi possível preparar a sincronização. Tente novamente.',
            'error' => app()->environment('production') ? null : $e->getMessage(),
            'finished_at' => now()->toIso8601String(),
        ], 5);

        Log::error('SYNC_FALHOU', [
            'tenant' => $this->tenant->slug,
            'erro' => $e->getMessage(),
        ]);
    }
}
