<?php

namespace Tests\Feature;

use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EquipeAcessoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pode_bloquear_e_reativar_login_de_recepcionista(): void
    {
        $admin = User::factory()->create();
        $recepcionista = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Equipe Teste',
            'slug' => 'equipe-teste',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($admin->id, ['papel' => 'admin', 'ativo' => true]);
        $tenant->users()->attach($recepcionista->id, ['papel' => 'recepcionista', 'ativo' => true]);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->patch(route('tenant.equipe.toggle-ativo', $recepcionista))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $recepcionista->id,
            'ativo' => false,
        ]);

        // Mesmo com uma sessão existente, a recepcionista bloqueada não acessa o painel.
        $this->actingAs($recepcionista)->withSession(['tenant_id' => $tenant->id])
            ->get(route('tenant.dashboard'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->patch(route('tenant.equipe.toggle-ativo', $recepcionista))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $recepcionista->id,
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

    public function test_admin_pode_criar_e_excluir_login_de_recepcionista(): void
    {
        $password = Str::random(24);
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
                'name' => 'Ana Recepcionista',
                'email' => 'ana@example.test',
                'password' => $password,
                'papel' => 'recepcionista',
            ])
            ->assertRedirect();

        $recepcionista = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $recepcionista->id,
            'papel' => 'recepcionista',
            'ativo' => true,
        ]);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->delete(route('tenant.equipe.destroy', $recepcionista))
            ->assertRedirect();

        $this->assertDatabaseMissing('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $recepcionista->id,
        ]);
        $this->assertDatabaseMissing('users', ['id' => $recepcionista->id]);
    }

    public function test_admin_cria_login_vinculado_ao_profissional(): void
    {
        $password = Str::random(24);
        $admin = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Barbearia Equipe',
            'slug' => 'barbearia-equipe',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($admin->id, ['papel' => 'admin', 'ativo' => true]);
        $profissional = Profissional::create(['tenant_id' => $tenant->id, 'nome' => 'João', 'ativo' => true]);

        $this->actingAs($admin)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.equipe.store'), [
                'name' => 'João Barbeiro',
                'email' => 'joao@example.test',
                'password' => $password,
                'papel' => 'profissional',
                'profissional_id' => $profissional->id,
            ])
            ->assertRedirect();

        $user = User::where('email', 'joao@example.test')->firstOrFail();
        $this->assertDatabaseHas('tenant_users', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'papel' => 'profissional',
        ]);
        $this->assertDatabaseHas('profissionais', ['id' => $profissional->id, 'user_id' => $user->id]);
    }
}
