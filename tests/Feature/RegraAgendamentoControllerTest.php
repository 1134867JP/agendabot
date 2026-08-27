<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegraAgendamentoControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Regras',
            'slug' => 'barbearia-regras',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);
    }

    private function autenticarComTenant()
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }

    public function test_index_retorna_config_com_defaults(): void
    {
        $response = $this->autenticarComTenant()->get(route('tenant.regras-agendamento.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tenant/RegrasAgendamento')
            ->where('config.antecedencia_minima_minutos', 30)
            ->where('config.antecedencia_maxima_dias', 60)
        );
    }

    public function test_update_persiste_regras_de_agendamento(): void
    {
        $response = $this->autenticarComTenant()->put(route('tenant.regras-agendamento.update'), [
            'antecedencia_minima_minutos' => 120,
            'antecedencia_maxima_dias' => 30,
            'buffer_entre_agendamentos_minutos' => 15,
            'permite_cliente_remarcar' => false,
            'permite_cliente_cancelar' => true,
            'politica_cancelamento' => 'Sem reembolso com menos de 2h.',
        ]);

        $response->assertRedirect();

        $this->tenant->refresh();
        $this->assertSame([
            'antecedencia_minima_minutos' => 120,
            'antecedencia_maxima_dias' => 30,
            'buffer_entre_agendamentos_minutos' => 15,
            'permite_cliente_remarcar' => false,
            'permite_cliente_cancelar' => true,
            'politica_cancelamento' => 'Sem reembolso com menos de 2h.',
        ], $this->tenant->regrasAgendamentoConfig());
    }
}
