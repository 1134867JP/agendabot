<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Recurso;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuadraAgendaIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Profissional $profissionalLegado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Society Teste',
            'slug' => 'society-teste',
            'tipo_servico' => 'quadra',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);

        // Simula um registro antigo ou criado por uma configuração incorreta.
        // Ele jamais deve virar fallback da agenda de uma quadra/society.
        $this->profissionalLegado = Profissional::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Carlos',
            'ativo' => true,
        ]);
    }

    private function autenticarComTenant(): static
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }

    public function test_society_sem_quadras_nao_expoe_profissional_como_fallback(): void
    {
        $this->autenticarComTenant()
            ->get(route('tenant.agenda'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Agenda')
                ->where('tenant.id', $this->tenant->id)
                ->has('recursos', 0)
                ->has('profissionais', 0)
                ->has('servicos', 0));

        $this->autenticarComTenant()
            ->get(route('tenant.agendamentos.index', ['novo' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Agendamentos/Index')
                ->has('recursos', 0)
                ->has('profissionais', 0)
                ->has('servicos', 0));
    }

    public function test_society_expoe_somente_as_quadras_do_tenant_atual(): void
    {
        Recurso::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Quadra principal',
            'duracao_padrao_minutos' => 60,
            'ativo' => true,
        ]);

        $outroTenant = Tenant::create([
            'nome' => 'Outra arena',
            'slug' => 'outra-arena',
            'tipo_servico' => 'quadra',
            'ativo' => true,
        ]);
        Recurso::create([
            'tenant_id' => $outroTenant->id,
            'nome' => 'Quadra externa',
            'duracao_padrao_minutos' => 60,
            'ativo' => true,
        ]);

        $this->autenticarComTenant()
            ->get(route('tenant.agenda'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recursos', 1)
                ->where('recursos.0.nome', 'Quadra principal')
                ->has('profissionais', 0));
    }

    public function test_society_rejeita_reserva_vinculada_a_profissional(): void
    {
        $inicio = now()->addDay()->setTime(10, 0);

        $response = $this->autenticarComTenant()->post(route('tenant.agendamentos.store'), [
            'profissional_id' => $this->profissionalLegado->id,
            'cliente_nome' => 'Cliente Teste',
            'cliente_telefone' => '51999999999',
            'inicio' => $inicio->toDateTimeString(),
            'fim' => $inicio->copy()->addHour()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors(['recurso_id', 'profissional_id']);
        $this->assertDatabaseMissing(Agendamento::class, ['cliente_nome' => 'Cliente Teste']);
    }
}
