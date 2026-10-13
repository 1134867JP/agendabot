<?php

namespace Tests\Feature;

use App\Jobs\ExpirarConversasInativasJob;
use App\Models\Conversa;
use App\Models\OutboundMessage;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RespostaPeloCelularTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Celular',
            'slug' => 'barbearia-celular',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'bot_ativo' => true,
            'whatsapp_conectado' => true,
            'evolution_instance' => 'barbearia-celular',
            'webhook_token' => 'token-celular',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function test_resposta_digitada_no_celular_pausa_o_bot(): void
    {
        $this->enviarWebhook('Oi, quero cortar amanhã', fromMe: false, id: 'CLIENTE-1');
        $this->enviarWebhook('Fala, mano! Amanhã 15h tá livre', fromMe: true, id: 'CELULAR-1');

        $conversa = Conversa::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('em_atendimento_humano', $conversa->status_v2);
        $this->assertDatabaseHas('mensagens', [
            'conversa_id' => $conversa->id,
            'remetente' => 'humano',
            'conteudo' => 'Fala, mano! Amanhã 15h tá livre',
            'evolution_message_id' => 'CELULAR-1',
        ]);
    }

    public function test_eco_de_mensagem_enviada_pelo_sistema_nao_pausa_o_bot(): void
    {
        $this->enviarWebhook('Oi', fromMe: false, id: 'CLIENTE-2');
        OutboundMessage::create([
            'tenant_id' => $this->tenant->id,
            'telefone' => '5551999999999',
            'conteudo' => 'Lembrete do seu corte',
            'purpose' => 'appointment_reminder',
            'idempotency_key' => 'teste-eco-id',
            'status' => OutboundMessage::STATUS_SENT,
            'provider_message_id' => 'ENVIADA-PELO-SISTEMA',
        ]);

        $this->enviarWebhook('Lembrete do seu corte', fromMe: true, id: 'ENVIADA-PELO-SISTEMA');

        $conversa = Conversa::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('ativa', $conversa->status_v2);
        $this->assertDatabaseMissing('mensagens', ['remetente' => 'humano']);
    }

    public function test_eco_que_chega_antes_do_id_ser_gravado_nao_pausa_o_bot(): void
    {
        $this->enviarWebhook('Oi', fromMe: false, id: 'CLIENTE-3');
        OutboundMessage::create([
            'tenant_id' => $this->tenant->id,
            'telefone' => '5551999999999',
            'conteudo' => 'Tenho 10h ou 14h, qual prefere?',
            'purpose' => 'conversation',
            'idempotency_key' => 'teste-eco-corrida',
            'status' => OutboundMessage::STATUS_PROCESSING,
        ]);

        $this->enviarWebhook('Tenho 10h ou 14h, qual prefere?', fromMe: true, id: 'ID-AINDA-DESCONHECIDO');

        $this->assertSame('ativa', Conversa::where('tenant_id', $this->tenant->id)->value('status_v2'));
    }

    public function test_bot_volta_a_atender_depois_de_inatividade_no_atendimento_humano(): void
    {
        config(['bot.retomar_apos_humano_minutos' => 120]);
        $this->enviarWebhook('Oi', fromMe: false, id: 'CLIENTE-4');
        $this->enviarWebhook('Já te respondo', fromMe: true, id: 'CELULAR-4');

        $conversa = Conversa::where('tenant_id', $this->tenant->id)->firstOrFail();
        $conversa->update(['ultima_mensagem_em' => now()->subMinutes(90)]);
        (new ExpirarConversasInativasJob)->handle();
        $this->assertSame('em_atendimento_humano', $conversa->fresh()->status_v2);

        $conversa->update(['ultima_mensagem_em' => now()->subMinutes(121)]);
        (new ExpirarConversasInativasJob)->handle();
        $this->assertSame('encerrada', $conversa->fresh()->status_v2);
    }

    private function enviarWebhook(string $texto, bool $fromMe, string $id): void
    {
        $this->withHeader('X-Webhook-Token', 'token-celular')
            ->postJson("/webhook/{$this->tenant->slug}", [
                'event' => 'messages.upsert',
                'data' => [
                    'key' => [
                        'remoteJid' => '5551999999999@s.whatsapp.net',
                        'fromMe' => $fromMe,
                        'id' => $id,
                    ],
                    'pushName' => $fromMe ? 'Dono da Barbearia' : 'Carlos',
                    'messageType' => 'conversation',
                    'message' => ['conversation' => $texto],
                ],
            ])
            ->assertOk();
    }
}
