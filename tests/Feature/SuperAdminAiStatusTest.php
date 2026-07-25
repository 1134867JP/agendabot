<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TokenUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SuperAdminAiStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.superadmin_two_factor' => false,
            'ai.default_provider' => 'claude',
            'ai.fallback_providers' => ['gemini'],
            'ai.providers.claude.key' => 'test-key',
            'ai.providers.claude.model' => 'claude-haiku-test',
            'ai.providers.gemini.key' => null,
            'ai.providers.gemini.model' => 'gemini-test',
        ]);
    }

    public function test_superadmin_visualiza_ia_configurada_e_ultima_ia_usada(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $tenant = Tenant::create([
            'nome' => 'Tenant IA',
            'slug' => 'tenant-ia',
            'tipo_servico' => 'personalizado',
            'ativo' => true,
        ]);

        TokenUsage::create([
            'tenant_id' => $tenant->id,
            'provider' => 'claude',
            'model' => 'claude-haiku-real',
            'input_tokens' => 10,
            'output_tokens' => 5,
            'created_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('superadmin.tokens'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/TokenUsage')
                ->where('ia.padrao.label', 'Claude')
                ->where('ia.padrao.model', 'claude-haiku-test')
                ->where('ia.padrao.configurado', true)
                ->where('ia.fallbacks.0.label', 'Gemini')
                ->where('ia.fallbacks.0.configurado', false)
                ->where('ia.ultima_chamada.label', 'Claude')
                ->where('ia.ultima_chamada.model', 'claude-haiku-real')
            );
    }

    public function test_usuario_comum_nao_acessa_status_de_ia_do_superadmin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('superadmin.tokens'))
            ->assertForbidden();
    }
}
