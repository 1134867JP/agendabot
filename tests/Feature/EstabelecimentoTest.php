<?php

namespace Tests\Feature;

use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EstabelecimentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_de_novo_estabelecimento_renderiza(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tenants.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Estabelecimentos/Create'));
    }

    public function test_usuario_existente_adiciona_estabelecimento(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tenants.store'), [
            'nome' => 'Segunda Barbearia',
            'tipo_servico' => 'barbeiro',
        ]);

        $response->assertRedirect(route('tenant.dashboard'));

        $this->assertDatabaseHas('tenants', ['nome' => 'Segunda Barbearia', 'tipo_servico' => 'barbeiro']);

        $tenant = $user->tenants()->firstWhere('nome', 'Segunda Barbearia');
        $this->assertNotNull($tenant);
        $this->assertSame('admin', $tenant->pivot->papel);
        $this->assertNotNull($tenant->webhook_token);
        $this->assertSame($tenant->id, session('tenant_id'));

        Queue::assertPushed(CreateEvolutionInstanceJob::class);
    }
}
