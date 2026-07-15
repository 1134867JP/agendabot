<?php

namespace Tests\Feature;

use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function dadosStep1(array $overrides = []): array
    {
        return array_merge([
            'nome_usuario' => 'Dono Teste',
            'email' => 'dono@example.com',
            'senha' => 'senha12345',
            'senha_confirmation' => 'senha12345',
            'nome_estabelecimento' => 'Barbearia Onboarding',
            'tipo_servico' => 'barbeiro',
            'telefone' => '51999999999',
        ], $overrides);
    }

    public function test_fluxo_feliz_completo(): void
    {
        Queue::fake();

        $response = $this->post('/cadastro', $this->dadosStep1());
        $response->assertRedirect(route('onboarding.step2'));
        Queue::assertPushed(CreateEvolutionInstanceJob::class);

        $tenant = Tenant::where('slug', 'like', 'barbearia-onboarding-%')->firstOrFail();
        $this->assertDatabaseHas('tenant_users', ['tenant_id' => $tenant->id]);

        $response = $this->post(route('onboarding.checkout'), ['plano' => 'starter']);
        $response->assertRedirect(route('onboarding.step3'));
        $this->assertSame('starter', $tenant->fresh()->plano);
        $this->assertNull($tenant->fresh()->asaas_subscription_id);

        $response = $this->post(route('onboarding.step3.store'), [
            'bot_nome' => 'Bia',
            'bot_saudacao' => 'Olá! Bem-vindo à barbearia, como posso ajudar?',
            'bot_tom' => 'semiformal',
        ]);
        $response->assertRedirect(route('onboarding.sucesso'));
        $fresh = $tenant->fresh();
        $this->assertSame('Bia', $fresh->nome_agente);
        // A saudação é persistida no campo próprio e NÃO sequestra instrucoes_extras.
        $this->assertSame('Olá! Bem-vindo à barbearia, como posso ajudar?', $fresh->bot_saudacao);
        $this->assertNull($fresh->instrucoes_extras);

        $this->get(route('onboarding.sucesso'))->assertOk();
    }

    public function test_step1_store_impede_email_duplicado(): void
    {
        Queue::fake();
        $this->post('/cadastro', $this->dadosStep1());

        $this->post('/cadastro', $this->dadosStep1(['nome_estabelecimento' => 'Outra Barbearia']))
            ->assertSessionHasErrors('email');
    }

    public function test_pular_pagamento_redireciona_step3(): void
    {
        Queue::fake();
        $this->post('/cadastro', $this->dadosStep1());

        $this->post(route('onboarding.pular'))
            ->assertRedirect(route('onboarding.step3'));
    }
}
