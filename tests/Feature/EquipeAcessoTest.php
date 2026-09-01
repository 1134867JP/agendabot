<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipeAcessoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_bloquear_e_reativar_login_de_atendente(): void
    {
        $admin = User::factory()->create();
        $atendente = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Equipe Teste',
            'slug' => 'equipe-teste',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($admin->id, ['papel' => 'admin', 'ativo' => true]);
        $tenant->users()->attach($atendente->id, ['papel' => 'operador', 'ativo' => true]);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->patch(route('tenant.equipe.toggle-ativo', $atendente))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $atendente->id,
            'ativo' => false,
        ]);

        // Mesmo com uma sessão existente, o atendente bloqueado não acessa o painel.
        $this->actingAs($atendente)->withSession(['tenant_id' => $tenant->id])
            ->get(route('tenant.dashboard'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->patch(route('tenant.equipe.toggle-ativo', $atendente))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $atendente->id,
            'ativo' => true,
        ]);
    }

    public function test_admin_nao_pode_bloquear_o_proprio_login(): void
    {
        $admin = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Equipe Protegida',
            'slug' => 'equipe-protegida',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($admin->id, ['papel' => 'admin', 'ativo' => true]);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->patch(route('tenant.equipe.toggle-ativo', $admin))
            ->assertStatus(422);

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'ativo' => true,
        ]);
    }

    public function test_admin_pode_criar_e_excluir_login_de_atendente(): void
    {
        $admin = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Equipe Login',
            'slug' => 'equipe-login',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($admin->id, ['papel' => 'admin', 'ativo' => true]);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.equipe.store'), [
                'name' => 'Ana Atendente',
                'email' => 'ana@example.test',
                'password' => 'Senha-provisoria-123',
                'papel' => 'operador',
            ])
            ->assertRedirect();

        $atendente = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $atendente->id,
            'papel' => 'operador',
            'ativo' => true,
        ]);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->delete(route('tenant.equipe.destroy', $atendente))
            ->assertRedirect();

        $this->assertDatabaseMissing('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $atendente->id,
        ]);
        $this->assertDatabaseMissing('users', ['id' => $atendente->id]);
    }
}
