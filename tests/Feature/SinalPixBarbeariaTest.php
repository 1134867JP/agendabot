<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\OutboundMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SinalPixBarbeariaTest extends TestCase
{
    use RefreshDatabase;

    private const CARTEIRA = '7f1c2a4e-9b3d-4c1a-8e2f-5a6b7c8d9e0f';

    private Tenant $tenant;

    private User $user;

    private Agendamento $agendamento;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['services.asaas.key' => 'chave-plataforma', 'services.asaas.base_url' => 'https://asaas.test/api/v3']);

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Sinal',
            'slug' => 'barbearia-sinal',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'whatsapp_conectado' => true,
            'evolution_instance' => 'barbearia-sinal',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);

        $inicio = now()->addDays(2)->setTime(15, 0);
        $this->agendamento = Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'cliente_nome' => 'Carlos',
            'cliente_telefone' => '5551999999999',
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(45),
            'data_hora' => $inicio,
            'duracao_minutos' => 45,
            'status' => 'confirmado',
            'origem' => 'manual',
        ]);
    }

    public function test_sem_carteira_cadastrada_nao_gera_cobranca(): void
    {
        Http::fake();

        $this->painel()->post(route('tenant.agendamentos.sinal', $this->agendamento), ['valor' => 20])
            ->assertSessionHasErrors('valor');

        Http::assertNothingSent();
    }

    public function test_sinal_vai_inteiro_para_a_carteira_da_barbearia_e_link_segue_pelo_whatsapp(): void
    {
        $this->painel()->put(route('tenant.configuracoes.pagamentos'), ['asaas_wallet_id' => self::CARTEIRA])
            ->assertSessionHasNoErrors();

        Http::fake([
            'asaas.test/api/v3/customers' => Http::response(['id' => 'cus_1']),
            'asaas.test/api/v3/payments' => Http::response(['id' => 'pay_1', 'invoiceUrl' => 'https://asaas.test/i/pay_1']),
        ]);

        $this->painel()->post(route('tenant.agendamentos.sinal', $this->agendamento), ['valor' => 20, 'enviar_whatsapp' => true])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payments')
            && $request['split'] === [['walletId' => self::CARTEIRA, 'percentualValue' => 100]]
            && (float) $request['value'] === 20.0);

        $this->assertSame('pending', $this->agendamento->fresh()->deposit_status);
        $mensagem = OutboundMessage::where('purpose', 'deposit_request')->firstOrFail();
        $this->assertStringContainsString('https://asaas.test/i/pay_1', $mensagem->conteudo);
    }

    public function test_carteira_invalida_e_recusada(): void
    {
        $this->painel()->put(route('tenant.configuracoes.pagamentos'), ['asaas_wallet_id' => 'minha-chave-pix'])
            ->assertSessionHasErrors('asaas_wallet_id');
    }

    private function painel()
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }
}
