<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmMiddlewareRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_impersonacao_nao_exige_confirmacao_de_senha(): void
    {
        $superAdmin = User::factory()->create(['role' => 'superadmin']);
        $tenant = Tenant::create([
            'nome' => 'Tenant de teste',
            'slug' => 'tenant-de-teste',
            'tipo_servico' => 'clinica',
            'ativo' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->post(route('superadmin.tenants.impersonar', $tenant));

        $response->assertRedirect(route('tenant.dashboard'));
        $response->assertSessionHas('tenant_id', $tenant->id);
    }
}
