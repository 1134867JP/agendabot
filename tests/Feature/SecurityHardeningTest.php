<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'nome' => 'Tenant de Segurança',
            'slug' => 'tenant-seguranca-' . uniqid(),
            'webhook_token' => 'token-secreto-de-teste',
            'ativo' => true,
            'bot_ativo' => false,
        ], $overrides));
    }

    public function test_webhook_nao_aceita_token_na_query_string(): void
    {
        $tenant = $this->tenant();

        $response = $this->postJson(route('webhook', $tenant->slug) . '?token=' . $tenant->webhook_token, [
            'event' => 'MESSAGES_UPSERT',
            'data' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_aceita_token_no_header(): void
    {
        $tenant = $this->tenant();

        $response = $this->withHeader('X-Webhook-Token', $tenant->webhook_token)
            ->postJson(route('webhook', $tenant->slug), [
                'event' => 'MESSAGES_UPSERT',
                'data' => [],
            ]);

        $response->assertOk();
    }

    public function test_webhook_nao_grava_conteudo_da_mensagem_no_log(): void
    {
        Log::spy();
        $tenant = $this->tenant(['bot_ativo' => true]);

        $this->withHeader('X-Webhook-Token', $tenant->webhook_token)
            ->postJson(route('webhook', $tenant->slug), [
                'event' => 'MESSAGES_UPSERT',
                'data' => [
                    'key' => [
                        'id' => 'msg-security-test',
                        'remoteJid' => '5511999999999@s.whatsapp.net',
                        'fromMe' => false,
                    ],
                    'messageType' => 'conversation',
                    'message' => ['conversation' => 'conteudo-privado-nao-deve-ir-para-log'],
                ],
            ]);

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $event, array $context) =>
                $event === 'WEBHOOK_MESSAGE_ACCEPTED'
                && ! array_key_exists('mensagem', $context)
                && ! array_key_exists('telefone', $context)
                && ! array_key_exists('push_name', $context)
            );
    }

    public function test_sessao_de_impersonacao_nao_concede_acesso_a_usuario_comum(): void
    {
        $user = User::create([
            'name' => 'Usuário comum',
            'email' => 'comum-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $tenant = $this->tenant();

        $this->actingAs($user)->withSession([
            'tenant_id' => $tenant->id,
            'impersonando_tenant_id' => $tenant->id,
        ])->get(route('tenant.dashboard'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_webhook_de_pagamento_de_sinal_ignora_agendamento_nao_vinculado_ao_pagamento(): void
    {
        config(['services.asaas.webhook_secret' => 'secret-test']);

        $response = $this->withHeader('asaas-access-token', 'secret-test')
            ->postJson(route('asaas.webhook'), [
                'id' => 'evt-security-test',
                'event' => 'PAYMENT_RECEIVED',
                'payment' => [
                    'id' => 'pay-nao-vinculado',
                    'externalReference' => 'deposit_agendamento_999999',
                    'value' => 100,
                ],
            ]);

        $response->assertOk();
    }
}
