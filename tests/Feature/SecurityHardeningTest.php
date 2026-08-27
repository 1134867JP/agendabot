<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WhatsAppConversationBackupService;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'nome' => 'Tenant de Segurança',
            'slug' => 'tenant-seguranca-'.uniqid(),
            'tipo_servico' => 'barbeiro',
            'webhook_token' => 'token-secreto-de-teste',
            'ativo' => true,
            'bot_ativo' => false,
        ], $overrides));
    }

    public function test_webhook_nao_aceita_token_na_query_string(): void
    {
        $tenant = $this->tenant();

        $response = $this->postJson(route('webhook', $tenant->slug).'?token='.$tenant->webhook_token, [
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
            ->withArgs(fn (string $event, array $context) => $event === 'WEBHOOK_MESSAGE_ACCEPTED'
                && ! array_key_exists('mensagem', $context)
                && ! array_key_exists('telefone', $context)
                && ! array_key_exists('push_name', $context)
            );
    }

    public function test_sessao_de_impersonacao_nao_concede_acesso_a_usuario_comum(): void
    {
        $user = User::create([
            'name' => 'Usuário comum',
            'email' => 'comum-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);
        $tenant = $this->tenant();

        $this->actingAs($user)->withSession([
            'tenant_id' => $tenant->id,
            'impersonando_tenant_id' => $tenant->id,
        ])->get(route('tenant.dashboard'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_respostas_web_incluem_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_hsts_ausente_em_requisicao_http_simples(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_hsts_presente_em_requisicao_https(): void
    {
        $response = $this->get('https://localhost/login');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_rate_limit_do_webhook_e_isolado_por_tenant(): void
    {
        $tenantA = $this->tenant(['slug' => 'tenant-a-'.uniqid()]);
        $tenantB = $this->tenant(['slug' => 'tenant-b-'.uniqid()]);

        $payload = ['event' => 'MESSAGES_UPSERT', 'data' => []];

        // Esgotar o bucket do tenant A preenchendo a mesma chave que o middleware
        // throttle usa para o limiter nomeado: md5({nome do limiter}.{chave do Limit::by})
        $chaveTenantA = md5('evolution-webhook'.'evolution-webhook:'.$tenantA->slug);
        for ($i = 0; $i < 240; $i++) {
            RateLimiter::hit($chaveTenantA, 60);
        }

        // Tenant A estourou o limite → 429 (a chave acima precisa bater com a do middleware)
        $this->withHeader('X-Webhook-Token', $tenantA->webhook_token)
            ->postJson(route('webhook', $tenantA->slug), $payload)
            ->assertStatus(429);

        // Tenant B, vindo do mesmo "IP", continua sendo atendido normalmente
        $this->withHeader('X-Webhook-Token', $tenantB->webhook_token)
            ->postJson(route('webhook', $tenantB->slug), $payload)
            ->assertOk();
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

    public function test_tenant_secrets_are_never_serialized(): void
    {
        $tenant = new Tenant([
            'nome' => 'Estabelecimento',
            'slug' => 'estabelecimento',
            'webhook_token' => 'segredo',
            'asaas_customer_id' => 'cus_123',
            'asaas_subscription_id' => 'sub_123',
        ]);

        $serialized = $tenant->toArray();

        $this->assertArrayNotHasKey('webhook_token', $serialized);
        $this->assertArrayNotHasKey('asaas_customer_id', $serialized);
        $this->assertArrayNotHasKey('asaas_subscription_id', $serialized);
    }

    public function test_super_admin_cannot_be_granted_by_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Usuário',
            'email' => 'usuario-'.uniqid().'@example.test',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_super_admin);
    }

    public function test_inertia_shared_props_use_an_explicit_allow_list(): void
    {
        $user = User::factory()->create();
        $tenant = new Tenant([
            'nome' => 'Estabelecimento',
            'slug' => 'estabelecimento',
            'webhook_token' => 'segredo',
        ]);

        app()->instance('tenant', $tenant);

        $request = Request::create('/painel', 'GET');
        $request->setUserResolver(fn () => $user);

        $props = app(HandleInertiaRequests::class)->share($request);
        $sharedTenant = $props['currentTenant']();
        $sharedUser = $props['auth']['user']();

        $this->assertArrayNotHasKey('webhook_token', $sharedTenant);
        $this->assertArrayNotHasKey('password', $sharedUser);
        $this->assertSame(
            ['id', 'name', 'email', 'telefone', 'is_super_admin'],
            array_keys($sharedUser),
        );
    }

    public function test_operator_is_forbidden_from_tenant_configuration(): void
    {
        $user = User::factory()->create();
        $tenant = $this->tenant(['subscription_status' => 'active']);
        $tenant->users()->attach($user->id, ['papel' => 'operador']);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->get(route('tenant.configuracoes.index'))
            ->assertForbidden();
    }

    public function test_whatsapp_backup_is_encrypted_at_rest(): void
    {
        Storage::fake('local');
        $tenant = $this->tenant(['nome' => 'Nome confidencial']);

        $backup = app(WhatsAppConversationBackupService::class)->criarBackup($tenant);
        $path = "whatsapp-backups/tenant-{$tenant->id}/{$backup['arquivo']}";

        Storage::disk('local')->assertExists($path);
        $this->assertStringNotContainsString(
            'Nome confidencial',
            Storage::disk('local')->get($path),
        );
        $this->assertStringContainsString(
            'Nome confidencial',
            app(WhatsAppConversationBackupService::class)->conteudo($tenant, $backup['arquivo']),
        );
    }

    public function test_csv_cells_that_can_execute_formulas_are_neutralized(): void
    {
        $this->assertSame(
            ["'=SUM(1,1)", "' +CMD", 'cliente normal', 10],
            Csv::row(['=SUM(1,1)', ' +CMD', 'cliente normal', 10]),
        );
    }
}
