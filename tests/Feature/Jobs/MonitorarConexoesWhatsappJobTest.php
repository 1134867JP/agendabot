<?php

namespace Tests\Feature\Jobs;

use App\Jobs\MonitorarConexoesWhatsappJob;
use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MonitorarConexoesWhatsappJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_sincroniza_estado_local_com_status_real_das_instancias(): void
    {
        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.key' => 'test-key',
        ]);

        $connected = $this->tenant('watchdog-connected', false);
        $disconnected = $this->tenant('watchdog-disconnected', true);
        Queue::fake();

        Http::fake([
            'https://evolution.test/instance/fetchInstances' => Http::response([
                ['name' => 'watchdog-connected', 'connectionStatus' => 'open'],
                ['name' => 'watchdog-disconnected', 'connectionStatus' => 'close'],
            ]),
        ]);

        MonitorarConexoesWhatsappJob::dispatchSync();

        $this->assertTrue($connected->fresh()->whatsapp_conectado);
        $this->assertFalse($disconnected->fresh()->whatsapp_conectado);
        $this->assertDatabaseHas('operational_events', [
            'tenant_id' => $connected->id,
            'type' => 'integration_recovered',
            'provider' => 'evolution',
        ]);
        Queue::assertPushed(SincronizarConversasWhatsappJob::class, 1);
        $this->assertDatabaseHas('operational_events', [
            'tenant_id' => $disconnected->id,
            'type' => 'integration_failure',
            'provider' => 'evolution',
        ]);
    }

    private function tenant(string $instance, bool $connected): Tenant
    {
        return Tenant::create([
            'nome' => $instance,
            'slug' => $instance,
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'whatsapp_conectado' => $connected,
            'evolution_instance' => $instance,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
