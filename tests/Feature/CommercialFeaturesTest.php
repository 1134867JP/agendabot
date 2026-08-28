<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RuntimeHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CommercialFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Teste', 'slug' => 'teste-comercial', 'tipo_servico' => 'barbeiro', 'ativo' => true,
            'subscription_status' => 'active', 'subscription_ends_at' => now()->addMonth(),
        ]);
        $tenant->users()->attach($user->id, ['papel' => 'admin']);

        return [$tenant, $user];
    }

    public function test_adiciona_cliente_na_lista_de_espera(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.waitlist.store'), [
                'cliente_nome' => 'Maria', 'cliente_telefone' => '5554999999999',
                'data_preferida' => now()->addDay()->format('Y-m-d'),
            ])->assertRedirect();

        $this->assertDatabaseHas('waitlist_entries', ['tenant_id' => $tenant->id, 'cliente_nome' => 'Maria']);
    }

    public function test_webhook_confirma_pagamento_do_sinal(): void
    {
        config(['services.asaas.webhook_secret' => 'secret']);
        [$tenant] = $this->tenantUser();
        $agendamento = Agendamento::create([
            'tenant_id' => $tenant->id, 'cliente_nome' => 'Maria', 'cliente_telefone' => '5554',
            'inicio' => now()->addDay(), 'fim' => now()->addDay()->addHour(), 'status' => 'confirmado',
            'deposit_status' => 'pending', 'deposit_payment_id' => 'pay-test-001', 'deposit_amount' => 10,
        ]);

        $this->postJson(route('asaas.webhook'), [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay-test-001', 'value' => 10, 'externalReference' => "deposit_agendamento_{$agendamento->id}"],
        ], ['asaas-access-token' => 'secret'])->assertOk();

        $this->assertSame('paid', $agendamento->fresh()->deposit_status);
    }

    public function test_health_check_valida_banco(): void
    {
        $this->getJson(route('health'))->assertOk()->assertJsonPath('checks.database', 'ok');
    }

    public function test_readiness_valida_workers_e_scheduler(): void
    {
        config(['queue.monitoring.workers' => ['interactive', 'batch']]);

        RuntimeHealth::touchWorker('interactive');
        RuntimeHealth::touchWorker('batch');
        RuntimeHealth::touchScheduler();

        $this->getJson(route('health.ready'))
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.runtime.workers.interactive.status', 'ok')
            ->assertJsonPath('checks.runtime.workers.batch.status', 'ok')
            ->assertJsonPath('checks.runtime.scheduler.status', 'ok');
    }

    public function test_readiness_falha_quando_um_processo_nao_tem_heartbeat(): void
    {
        Cache::flush();
        config(['queue.monitoring.workers' => ['interactive', 'batch']]);

        RuntimeHealth::touchWorker('interactive');
        RuntimeHealth::touchScheduler();

        $this->getJson(route('health.ready'))
            ->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('checks.runtime.workers.batch.status', 'missing');
    }
}
