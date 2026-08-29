<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebhookMaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::create(array_merge([
            'nome' => 'Empresa de teste',
            'slug' => 'empresa-teste',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'evolution_instance' => 'empresa-teste',
            'whatsapp_conectado' => true,
            'webhook_token' => str_repeat('a', 64),
        ], $attributes));
    }

    public function test_rotaciona_token_localmente_quando_instancia_remota_nao_existe(): void
    {
        $tenant = $this->tenant();
        $tokenAnterior = $tenant->webhook_token;

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('listarStatusInstancias')->once()->andReturn([]);
            $mock->shouldNotReceive('configurarWebhook');
        });

        $exitCode = Artisan::call('security:rotate-webhook-tokens', [
            '--stale' => true,
            '--force' => true,
        ]);

        $tenant->refresh();
        $this->assertSame(0, $exitCode);
        $this->assertNotSame($tokenAnterior, $tenant->webhook_token);
        $this->assertNotNull($tenant->webhook_token_rotated_at);
        $this->assertFalse($tenant->whatsapp_conectado);
    }

    public function test_reconfiguracao_ignora_referencia_remota_ausente_e_marca_desconectado(): void
    {
        $tenant = $this->tenant();

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('listarStatusInstancias')->once()->andReturn([]);
            $mock->shouldNotReceive('configurarWebhook');
        });

        $exitCode = Artisan::call('whatsapp:reconfigure-webhooks');

        $this->assertSame(0, $exitCode);
        $this->assertFalse($tenant->fresh()->whatsapp_conectado);
    }

    public function test_reconfiguracao_retorna_falha_quando_evolution_recusa_webhook(): void
    {
        $this->tenant();

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('listarStatusInstancias')->once()->andReturn([
                'empresa-teste' => 'open',
            ]);
            $mock->shouldReceive('configurarWebhook')->once()->andReturnFalse();
        });

        $exitCode = Artisan::call('whatsapp:reconfigure-webhooks');

        $this->assertSame(1, $exitCode);
    }
}
