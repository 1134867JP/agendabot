<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome'                => 'Barbearia Webhook',
            'slug'                => 'barbearia-webhook',
            'tipo_servico'        => 'barbeiro',
            'ativo'               => true,
            'bot_ativo'           => true,
            'whatsapp_conectado'  => true,
            'webhook_token'       => 'token-teste-webhook',
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    public function test_webhook_dispatch_job_para_mensagem_valida(): void
    {
        Queue::fake();

        $payload = $this->payloadEvolution('5551999999999@s.whatsapp.net', 'Quero agendar um corte');

        $response = $this->withHeader('X-Webhook-Token', 'token-teste-webhook')->postJson("/webhook/{$this->tenant->slug}", $payload);

        $response->assertStatus(200);
        Queue::assertPushed(ProcessarMensagemWhatsapp::class);
    }

    public function test_webhook_ignora_mensagem_propria(): void
    {
        Queue::fake();

        $payload = $this->payloadEvolution('5551999999999@s.whatsapp.net', 'Olá!', fromMe: true);

        $response = $this->withHeader('X-Webhook-Token', 'token-teste-webhook')->postJson("/webhook/{$this->tenant->slug}", $payload);

        $response->assertStatus(200);
        Queue::assertNothingPushed();
    }

    public function test_webhook_ignora_mensagem_de_grupo(): void
    {
        Queue::fake();

        $payload = $this->payloadEvolution('5551999999999-1234567890@g.us', 'Mensagem de grupo');
        $payload['data']['key']['participantAlt'] = '5551999999999@s.whatsapp.net';

        $response = $this->withHeader('X-Webhook-Token', 'token-teste-webhook')->postJson("/webhook/{$this->tenant->slug}", $payload);

        $response->assertStatus(200);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('conversas', 0);
        $this->assertDatabaseCount('mensagens', 0);
    }

    public function test_webhook_retorna_404_para_tenant_inexistente(): void
    {
        $response = $this->postJson('/webhook/tenant-que-nao-existe', []);

        $response->assertStatus(404);
    }

    public function test_reconexao_do_whatsapp_inicia_estado_e_dispara_sincronizacao(): void
    {
        Queue::fake();
        $this->tenant->update(['whatsapp_conectado' => false]);

        $response = $this->withHeader('X-Webhook-Token', 'token-teste-webhook')
            ->postJson("/webhook/{$this->tenant->slug}", [
                'event' => 'connection.update',
                'data' => ['state' => 'open'],
            ]);

        $response->assertOk();
        $this->assertTrue($this->tenant->fresh()->whatsapp_conectado);
        Queue::assertPushed(SincronizarConversasWhatsappJob::class);
    }

    public function test_webhook_salva_mensagem_sem_disparar_resposta_quando_bot_inativo(): void
    {
        Queue::fake();

        $this->tenant->update(['bot_ativo' => false]);

        $payload = $this->payloadEvolution('5551999999999@s.whatsapp.net', 'Oi');

        $response = $this->withHeader('X-Webhook-Token', 'token-teste-webhook')->postJson("/webhook/{$this->tenant->slug}", $payload);

        $response->assertStatus(200);
        Queue::assertNothingPushed();
        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $this->tenant->id,
            'telefone' => '5551999999999',
        ]);
        $this->assertDatabaseHas('mensagens', [
            'remetente' => 'cliente',
            'conteudo' => 'Oi',
        ]);
    }

    private function payloadEvolution(string $remoteJid, string $text, bool $fromMe = false): array
    {
        return [
            'event' => 'messages.upsert',
            'data'  => [
                'key' => [
                    'remoteJid' => $remoteJid,
                    'fromMe'    => $fromMe,
                    'id'        => 'MSG_' . uniqid(),
                ],
                'messageType' => 'conversation',
                'message'     => [
                    'conversation' => $text,
                ],
            ],
        ];
    }
}
