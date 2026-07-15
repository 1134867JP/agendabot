<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracoesHubTest extends TestCase
{
    use RefreshDatabase;

    private function tenantAdmin(string $tipo = 'barbeiro'): array
    {
        $user   = User::factory()->create();
        $tenant = Tenant::create([
            'nome'                => 'Config Hub',
            'slug'                => 'config-hub-'.uniqid(),
            'tipo_servico'        => $tipo,
            'ativo'               => true,
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $tenant->users()->attach($user->id, ['papel' => 'admin']);

        return [$user, $tenant];
    }

    /** Cada página do hub deve renderizar seu componente Inertia para um admin autenticado. */
    public function test_paginas_do_hub_renderizam(): void
    {
        [$user, $tenant] = $this->tenantAdmin('barbeiro');
        $auth = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);

        $rotas = [
            'tenant.configuracoes.index'      => 'Tenant/Configuracoes',
            'tenant.profissionais.index'      => 'Tenant/Profissionais/Index',
            'tenant.servicos.index'           => 'Tenant/Servicos/Index',
            'tenant.regras-agendamento.index' => 'Tenant/RegrasAgendamento',
            'tenant.triagem.index'            => 'Tenant/Triagem',
            'tenant.whatsapp'                 => 'Tenant/WhatsApp',
            'tenant.equipe.index'             => 'Tenant/Equipe',
        ];

        foreach ($rotas as $rota => $componente) {
            $auth->get(route($rota))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($componente));
        }
    }

    public function test_hub_de_tenant_quadra_expoe_recursos(): void
    {
        [$user, $tenant] = $this->tenantAdmin('quadra');

        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->get(route('tenant.recursos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tenant/Recursos/Index'));
    }
}
