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
            ->post(route('tenant.renovar.store'), [
                'plano' => 'pro',
                'ciclo' => 'mensal',
                'metodo' => 'PIX',
            ])
            ->assertRedirect('https://asaas.test/pix/123');

        $this->assertSame('pro', $tenant->fresh()->plano);
        $this->assertSame('cus_pix', $tenant->fresh()->asaas_customer_id);
    }

    public function test_cartao_anual_cria_assinatura_com_dez_mensalidades(): void
    {
        [$tenant, $user] = $this->tenantUser();
        Http::fake([
            '*/customers' => Http::response(['id' => 'cus_card'], 200),
            '*/subscriptions' => Http::response(['id' => 'sub_anual'], 200),
            '*/subscriptions/sub_anual/payments*' => Http::response([
                'data' => [['invoiceUrl' => 'https://asaas.test/card/123']],
            ], 200),
        ]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.renovar.store'), [
                'plano' => 'starter',
                'ciclo' => 'anual',
                'metodo' => 'CREDIT_CARD',
            ])
            ->assertRedirect('https://asaas.test/card/123');

        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/subscriptions')
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
}
