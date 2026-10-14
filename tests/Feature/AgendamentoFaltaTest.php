<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A falta pode ser registrada também depois que a rotina automática concluiu o atendimento. */
class AgendamentoFaltaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_falta_em_agendamento_ja_concluido(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Barbearia Falta',
            'slug' => 'barbearia-falta',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($user->id, ['papel' => 'admin']);
        $agendamento = Agendamento::create([
            'tenant_id' => $tenant->id,
            'cliente_nome' => 'Lucas',
            'cliente_telefone' => '5551944440000',
            'inicio' => now()->subDay(),
            'fim' => now()->subDay()->addMinutes(30),
            'status' => 'concluido',
            'origem' => 'manual',
        ]);

        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->patch(route('tenant.agendamentos.no-show', $agendamento))
            ->assertRedirect();

        $this->assertTrue($agendamento->fresh()->no_show);
        $this->assertSame('concluido', $agendamento->fresh()->status);
    }
}
