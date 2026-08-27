<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteCadastroRapidoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Clínica Cadastro',
            'slug' => 'clinica-cadastro-rapido',
            'tipo_servico' => 'clinica',
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

    public function test_cadastra_cliente_normalizando_telefone(): void
    {
        $this->autenticarComTenant()
            ->post(route('tenant.clientes.store'), [
                'nome' => 'Maria Silva',
                'telefone' => '(54) 99999-1234',
                'observacoes' => 'Prefere atendimento pela manhã.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $this->tenant->id,
            'nome' => 'Maria Silva',
            'telefone' => '5554999991234',
        ]);
    }

    public function test_impede_telefone_duplicado_no_mesmo_estabelecimento(): void
    {
        Cliente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Cliente existente',
            'telefone' => '5554999991234',
        ]);

        $this->autenticarComTenant()
            ->from(route('tenant.clientes.index'))
            ->post(route('tenant.clientes.store'), [
                'nome' => 'Outro cadastro',
                'telefone' => '54 99999-1234',
            ])
            ->assertRedirect(route('tenant.clientes.index'))
            ->assertSessionHasErrors('telefone');

        $this->assertSame(1, $this->tenant->clientes()->count());
    }

    public function test_mesmo_telefone_pode_existir_em_outro_tenant(): void
    {
        $outroTenant = Tenant::create([
            'nome' => 'Outra clínica',
            'slug' => 'outra-clinica-cadastro',
            'tipo_servico' => 'clinica',
            'ativo' => true,
        ]);
        Cliente::create([
            'tenant_id' => $outroTenant->id,
            'nome' => 'Cliente externo',
            'telefone' => '5554999991234',
        ]);

        $this->autenticarComTenant()
            ->post(route('tenant.clientes.store'), [
                'nome' => 'Cliente local',
                'telefone' => '(54) 99999-1234',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $this->tenant->id,
            'nome' => 'Cliente local',
            'telefone' => '5554999991234',
        ]);
    }
}
