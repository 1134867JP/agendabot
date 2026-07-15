<?php

namespace Tests\Feature;

use App\Models\Recurso;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecursoControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user   = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome'                => 'Arena Sports',
            'slug'                => 'arena-sports',
            'tipo_servico'        => 'quadra',
            'ativo'               => true,
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);
    }

    private function autenticarComTenant()
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }

    public function test_destroy_desativa_em_vez_de_apagar(): void
    {
        $recurso = Recurso::create([
            'tenant_id' => $this->tenant->id,
            'nome'      => 'Quadra de Futsal',
            'ativo'     => true,
        ]);

        $response = $this->autenticarComTenant()->delete(route('tenant.recursos.destroy', $recurso->id));

        $response->assertRedirect();
        // O registro continua no banco (histórico preservado), apenas inativo.
        $this->assertDatabaseHas('recursos', [
            'id'    => $recurso->id,
            'ativo' => false,
        ]);
    }
}
