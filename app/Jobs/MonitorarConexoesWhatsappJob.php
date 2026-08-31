<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\OperationalEvent;
use App\Models\Tenant;
use App\Services\EvolutionApiService;
use App\Services\WhatsAppSyncState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MonitorarConexoesWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RegistraFalha, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function handle(EvolutionApiService $evolution, WhatsAppSyncState $syncState): void
    {
        $statuses = $evolution->listarStatusInstancias();

        Tenant::query()
            ->where('ativo', true)
            ->whereNotNull('evolution_instance')
            ->where('evolution_instance', '!=', '')
            ->chunkById(200, function ($tenants) use ($statuses): void {
                foreach ($tenants as $tenant) {
                    $status = $statuses[$tenant->evolution_instance] ?? null;
                    if (! in_array($status, ['open', 'close', 'connecting'], true)) {
                        continue;
                    }

                    $connected = $status === 'open';
                    if ($tenant->whatsapp_conectado === $connected) {
                        continue;
                    }

                    $wasConnected = $tenant->whatsapp_conectado;
                    $tenant->update(['whatsapp_conectado' => $connected]);

                    OperationalEvent::record($tenant->id, $connected ? 'integration_recovered' : 'integration_failure', [
                        'provider' => 'evolution',
                        'metadata' => [
                            'message' => $connected
                                ? 'WhatsApp reconectado.'
                                : "WhatsApp desconectado (status: {$status}).",
                            'operation' => 'connection_watchdog',
                            'status' => $status,
                        ],
                    ]);

                    $context = [
                        'tenant_id' => $tenant->id,
                        'instance' => $tenant->evolution_instance,
                        'previously_connected' => $wasConnected,
                        'status' => $status,
                    ];

                    if ($connected) {
                        $executionId = $syncState->iniciar($tenant);
                        SincronizarConversasWhatsappJob::dispatch($tenant, $executionId)->onQueue('sync');
                        Log::channel('jobs')->info('WHATSAPP_CONNECTION_CHANGED', $context);
                    } else {
                        Log::channel('jobs')->warning('WHATSAPP_CONNECTION_CHANGED', $context);
                    }
                }
            });
    }

    public function failed(Throwable $e): void
    {
        $this->registrarFalha($e, null, ['evento' => 'monitorar_conexoes_whatsapp']);
    }
}
