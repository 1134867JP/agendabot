<?php

namespace Tests\Feature;

use App\Mail\AssinaturaPendenteMail;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AsaasWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SEGREDO = 'segredo-teste-asaas';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.asaas.webhook_secret' => self::SEGREDO]);

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Asaas',
            'slug' => 'barbearia-asaas',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'asaas_customer_id' => 'cus_000001',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    private function payload(string $event, array $paymentOverrides = []): array
    {
        return [
            'id' => 'evt_'.strtolower($event),
            'event' => $event,
            'payment' => array_merge([
                'id' => 'pay_000001',
                'customer' => 'cus_000001',
                'value' => 79.90,
            ], $paymentOverrides),
        ];
    }

    public function test_rejeita_payload_sem_token_valido(): void
    {
        $response = $this->postJson(route('asaas.webhook'), $this->payload('PAYMENT_CONFIRMED'));

        $response->assertStatus(401);
        $this->assertSame(0, SubscriptionEvent::count());
        $this->assertSame('trial', $this->tenant->fresh()->subscription_status);
    }

    public function test_rejeita_payload_com_token_incorreto(): void
    {
        $response = $this->postJson(route('asaas.webhook'), $this->payload('PAYMENT_CONFIRMED'), [
            'asaas-access-token' => 'token-errado',
        ]);

        $response->assertStatus(401);
        $this->assertSame(0, SubscriptionEvent::count());
    }

    public function test_payment_confirmed_ativa_tenant(): void
    {
        $response = $this->postJson(route('asaas.webhook'), $this->payload('PAYMENT_CONFIRMED'), [
            'asaas-access-token' => self::SEGREDO,
        ]);

        $response->assertOk();
        $this->tenant->refresh();
        $this->assertSame('active', $this->tenant->subscription_status);
        $this->assertDatabaseHas('subscription_events', [
            'tenant_id' => $this->tenant->id,
            'tipo' => 'PAYMENT_CONFIRMED',
        ]);
    }

    public function test_payment_received_ativa_tenant(): void
    {
        $response = $this->postJson(route('asaas.webhook'), $this->payload('PAYMENT_RECEIVED'), [
            'asaas-access-token' => self::SEGREDO,
        ]);

        $response->assertOk();
        $this->assertSame('active', $this->tenant->fresh()->subscription_status);
    }

    public function test_payment_overdue_marca_past_due_e_envia_email(): void
    {
        Mail::fake();

        $response = $this->postJson(route('asaas.webhook'), $this->payload('PAYMENT_OVERDUE'), [
            'asaas-access-token' => self::SEGREDO,
        ]);

        $response->assertOk();
        $this->assertSame('past_due', $this->tenant->fresh()->subscription_status);
        Mail::assertQueued(AssinaturaPendenteMail::class);
    }

    public function test_evento_repetido_e_processado_apenas_uma_vez(): void
    {
        Mail::fake();
        $payload = $this->payload('PAYMENT_OVERDUE');
        $headers = ['asaas-access-token' => self::SEGREDO];

        $this->postJson(route('asaas.webhook'), $payload, $headers)->assertOk();
        $this->postJson(route('asaas.webhook'), $payload, $headers)->assertOk();

        $this->assertSame(1, SubscriptionEvent::count());
        $this->assertDatabaseHas('subscription_events', [
            'event_id' => 'evt_payment_overdue',
        ]);
        Mail::assertQueued(AssinaturaPendenteMail::class, 1);
    }

    public function test_subscription_deleted_cancela_tenant(): void
    {
        $response = $this->postJson(route('asaas.webhook'), $this->payload('SUBSCRIPTION_DELETED'), [
            'asaas-access-token' => self::SEGREDO,
        ]);

        $response->assertOk();
        $this->assertSame('canceled', $this->tenant->fresh()->subscription_status);
    }

    public function test_evento_de_customer_desconhecido_retorna_ok_sem_efeito(): void
    {
        $response = $this->postJson(route('asaas.webhook'), $this->payload('PAYMENT_CONFIRMED', [
            'customer' => 'cus_inexistente',
        ]), [
            'asaas-access-token' => self::SEGREDO,
        ]);

        $response->assertOk();
        $this->assertSame(0, SubscriptionEvent::count());
        $this->assertSame('trial', $this->tenant->fresh()->subscription_status);
    }
}
