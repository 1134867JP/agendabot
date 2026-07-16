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

    private function contexto(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Clínica Cadastro Rápido',
            'slug' => 'clinica-cadastro-rapido-'.uniqid(),
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($user->id, ['papel' => 'admin']);

        return [$user, $tenant];
    }

    public function test_cadastra_cliente_normalizando_telefone(): void
    {
        [$user, $tenant] = $this->contexto();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.clientes.store'), [
                'nome' => 'Maria Silva',
                'telefone' => '(54) 99999-1234',
                'observacoes' => 'Prefere atendimento pela manhã.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $tenant->id,
            'nome' => 'Maria Silva',
            'telefone' => '5554999991234',
        ]);
    }

    public function test_impede_telefone_duplicado_no_mesmo_estabelecimento(): void
    {
        [$user, $tenant] = $this->contexto();

        Cliente::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Cliente existente',
            'telefone' => '5554999991234',
        ]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->from(route('tenant.clientes.index'))
            ->post(route('tenant.clientes.store'), [
                'nome' => 'Outro cadastro',
                'telefone' => '54 99999-1234',
            ])
            ->assertRedirect(route('tenant.clientes.index'))
            ->assertSessionHasErrors('telefone');

        $this->assertSame(1, $tenant->clientes()->count());
    }
}
