<?php

namespace Tests\Feature;

use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_001'], 200),
            '*/subscriptions/*/paymentLink' => Http::response(['url' => 'https://asaas.test/pay/xyz'], 200),
            '*/subscriptions' => Http::response(['id' => 'sub_001'], 200),
        ]);

        // Step 1: cria usuário + tenant
        $response = $this->post('/cadastro', $this->dadosStep1());
        $response->assertRedirect(route('onboarding.step2'));
        Queue::assertPushed(CreateEvolutionInstanceJob::class);

        $tenant = Tenant::where('slug', 'like', 'barbearia-onboarding-%')->firstOrFail();
        $this->assertDatabaseHas('tenant_users', ['tenant_id' => $tenant->id]);

        // Step 2 -> checkout: sucesso do Asaas redireciona pro link de pagamento
        $response = $this->post(route('onboarding.checkout'), ['plano' => 'starter']);
        $response->assertRedirect('https://asaas.test/pay/xyz');
        $this->assertSame('sub_001', $tenant->fresh()->asaas_subscription_id);

        // Step 3: configura o bot
        $response = $this->post(route('onboarding.step3.store'), [
            'bot_nome' => 'Bia',
            'bot_saudacao' => 'Olá! Bem-vindo à barbearia, como posso ajudar?',
            'bot_tom' => 'semiformal',
        ]);
        $response->assertRedirect(route('onboarding.sucesso'));
        $this->assertSame('Bia', $tenant->fresh()->nome_agente);

        // Sucesso
        $response = $this->get(route('onboarding.sucesso'));
        $response->assertOk();
    }

    public function test_checkout_com_falha_do_asaas_nao_bloqueia_onboarding(): void
    {
        Queue::fake();
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_002'], 200),
            '*/subscriptions' => Http::response(['errors' => ['Erro simulado']], 500),
        ]);

        $this->post('/cadastro', $this->dadosStep1(['email' => 'dono2@example.com']));
        $tenant = Tenant::where('slug', 'like', 'barbearia-onboarding-%')->firstOrFail();

        $response = $this->post(route('onboarding.checkout'), ['plano' => 'starter']);

        // Não bloqueia: segue pro step3 mesmo com falha no Asaas
        $response->assertRedirect(route('onboarding.step3'));
        $this->assertNull($tenant->fresh()->asaas_subscription_id);
    }

    public function test_step1_store_impede_email_duplicado(): void
    {
        Queue::fake();

        $this->post('/cadastro', $this->dadosStep1());

        $response = $this->post('/cadastro', $this->dadosStep1(['nome_estabelecimento' => 'Outra Barbearia']));

        $response->assertSessionHasErrors('email');
    }

    public function test_pular_pagamento_redireciona_step3(): void
    {
        Queue::fake();
        $this->post('/cadastro', $this->dadosStep1());

        $response = $this->post(route('onboarding.pular'));

        $response->assertRedirect(route('onboarding.step3'));
    }
}
