<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Teste',
            'slug' => 'teste-pagamento',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'plano' => 'starter',
            'subscription_status' => 'past_due',
        ]);
        $tenant->users()->attach($user->id, ['papel' => 'admin']);

        return [$tenant, $user];
    }

    public function test_renovacao_pix_funciona_sem_assinatura_anterior(): void
    {
        [$tenant, $user] = $this->tenantUser();
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_pix'], 200),
            '*/payments' => Http::response([
                'id' => 'pay_pix',
                'invoiceUrl' => 'https://asaas.test/pix/123',
            ], 200),
        ]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('tenant.renovar.store'), [
                'plano' => 'pro',
                'ciclo' => 'mensal',
                'metodo' => 'PIX',
            ])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://asaas.test/pix/123');

        $this->assertSame('pro', $tenant->fresh()->plano);
        $this->assertSame('cus_pix', $tenant->fresh()->asaas_customer_id);
    }

    public function test_cartao_anual_cria_assinatura_com_dez_mensalidades(): void
    {
        [$tenant, $user] = $this->tenantUser();
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_card'], 200),
            '*/subscriptions/sub_anual/payments*' => Http::response([
                'data' => [['invoiceUrl' => 'https://asaas.test/card/123']],
            ], 200),
            '*/subscriptions' => Http::response(['id' => 'sub_anual'], 200),
        ]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('tenant.renovar.store'), [
                'plano' => 'starter',
                'ciclo' => 'anual',
                'metodo' => 'CREDIT_CARD',
            ])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://asaas.test/card/123');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/subscriptions')
            && $request['cycle'] === 'YEARLY'
            && (float) $request['value'] === 499.0
        );
        $this->assertSame('sub_anual', $tenant->fresh()->asaas_subscription_id);
    }

    public function test_webhook_ativa_periodo_anual(): void
    {
        config(['services.asaas.webhook_secret' => 'secret']);
        [$tenant] = $this->tenantUser();
        $tenant->update(['asaas_customer_id' => 'cus_webhook']);

        $this->postJson(route('asaas.webhook'), [
            'id' => 'evt_anual_1',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_anual',
                'customer' => 'cus_webhook',
                'value' => 499,
                'externalReference' => "assinatura_tenant_{$tenant->id}_starter_anual",
            ],
        ], ['asaas-access-token' => 'secret'])->assertOk();

        $tenant->refresh();
        $this->assertSame('active', $tenant->subscription_status);
        $this->assertSame('starter', $tenant->plano);
        $this->assertTrue($tenant->subscription_ends_at->greaterThan(now()->addMonths(11)));
    }

    public function test_repetir_checkout_pix_reutiliza_link_sem_criar_outra_cobranca(): void
    {
        [$tenant, $user] = $this->tenantUser();
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_idempotente'], 200),
            '*/payments' => Http::response([
                'id' => 'pay_idempotente',
                'invoiceUrl' => 'https://asaas.test/pix/idempotente',
            ], 200),
        ]);

        $payload = [
            'plano' => 'pro',
            'ciclo' => 'mensal',
            'metodo' => 'PIX',
        ];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->actingAs($user)
                ->withSession(['tenant_id' => $tenant->id])
                ->withHeaders([
                    'X-Inertia' => 'true',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post(route('tenant.renovar.store'), $payload)
                ->assertStatus(409)
                ->assertHeader('X-Inertia-Location', 'https://asaas.test/pix/idempotente');
        }

        $paymentRequests = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/payments'));
        $this->assertCount(1, $paymentRequests);
    }

    public function test_cancelamento_so_altera_estado_local_quando_asaas_confirma(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $tenant->update([
            'subscription_status' => 'active',
            'asaas_subscription_id' => 'sub_ativa',
        ]);
        Http::fake(['*/subscriptions/sub_ativa' => Http::response([], 200)]);

        $this->actingAs($user)
            ->withSession([
                'tenant_id' => $tenant->id,
                'auth.password_confirmed_at' => now()->timestamp,
            ])
            ->post(route('tenant.cancelar'))
            ->assertRedirect(route('tenant.renovar'));

        $tenant->refresh();
        $this->assertSame('canceled', $tenant->subscription_status);
        $this->assertNull($tenant->asaas_subscription_id);
    }

    public function test_falha_no_asaas_nao_marca_assinatura_como_cancelada(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $tenant->update([
            'subscription_status' => 'active',
            'asaas_subscription_id' => 'sub_ativa',
        ]);
        Http::fake(['*/subscriptions/sub_ativa' => Http::response(['errors' => [['description' => 'falha']]], 500)]);

        $this->actingAs($user)
            ->withSession([
                'tenant_id' => $tenant->id,
                'auth.password_confirmed_at' => now()->timestamp,
            ])
            ->from(route('tenant.renovar'))
            ->post(route('tenant.cancelar'))
            ->assertRedirect(route('tenant.renovar'))
            ->assertSessionHas('erro');

        $tenant->refresh();
        $this->assertSame('active', $tenant->subscription_status);
        $this->assertSame('sub_ativa', $tenant->asaas_subscription_id);
    }
}
