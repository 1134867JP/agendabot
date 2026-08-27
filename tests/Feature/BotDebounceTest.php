<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BotDebounceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Debounce',
            'slug' => 'barbearia-debounce',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'bot_ativo' => true,
            'whatsapp_conectado' => true,
            'webhook_token' => 'token-teste-debounce',
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    private function fakeClaudeEEvolution(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Claro! Para amanhã temos horários. 😊']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);
    }

    private function persistir(string $telefone, string $texto, string $evolutionId): Mensagem
    {
        return app(ConversaSyncService::class)->registrarMensagemRecebida(
            $this->tenant, $telefone, $texto, $evolutionId, 'Cliente Teste'
        );
    }

    public function test_duas_mensagens_rapidas_geram_uma_unica_resposta_com_historico_completo(): void
    {
        $this->fakeClaudeEEvolution();

        $telefone = '5551966000001';

        // Webhook persiste as duas mensagens antes de qualquer job rodar
        $msg1 = $this->persistir($telefone, 'oi', 'DEB_MSG_1');
        $msg2 = $this->persistir($telefone, 'quero marcar amanhã', 'DEB_MSG_2');

        // Job da 1ª mensagem roda e detecta que existe mensagem mais nova → não responde
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $msg1->id);

        $conversa = Conversa::where('tenant_id', $this->tenant->id)->where('telefone_cliente', $telefone)->first();
        $this->assertSame(0, $conversa->mensagens()->where('remetente', 'bot')->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));

        // Job da 2ª mensagem responde considerando o histórico completo
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $msg2->id);

        $this->assertSame(1, $conversa->mensagens()->where('remetente', 'bot')->count());

        $chamadasClaude = Http::recorded(fn ($request) => str_contains($request->url(), 'anthropic.com'));
        $this->assertCount(1, $chamadasClaude);

        // As duas mensagens do cliente foram mescladas num único turno 'user'
        $mensagensEnviadas = $chamadasClaude->first()[0]->data()['messages'];
        $ultimoTurnoUser = collect($mensagensEnviadas)->where('role', 'user')->last();
        $this->assertStringContainsString('oi', $ultimoTurnoUser['content']);
        $this->assertStringContainsString('quero marcar amanhã', $ultimoTurnoUser['content']);
    }

    public function test_mensagem_unica_responde_normalmente(): void
    {
        $this->fakeClaudeEEvolution();

        $telefone = '5551966000002';
        $msg = $this->persistir($telefone, 'oi, tem horário amanhã?', 'DEB_MSG_UNICA');

        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $msg->id);

        $conversa = Conversa::where('tenant_id', $this->tenant->id)->where('telefone_cliente', $telefone)->first();
        $this->assertSame(1, $conversa->mensagens()->where('remetente', 'bot')->count());
        Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));
    }

    public function test_retry_nao_responde_de_novo_quando_bot_ja_respondeu_depois_da_mensagem(): void
    {
        $this->fakeClaudeEEvolution();

        $telefone = '5551966000003';
        $msg = $this->persistir($telefone, 'oi', 'DEB_MSG_RETRY');

        // Simula uma tentativa anterior que já salvou a resposta do bot
        $conversa = Conversa::where('tenant_id', $this->tenant->id)->where('telefone_cliente', $telefone)->first();
        $conversa->registrarMensagem('bot', 'Olá! Como posso ajudar?');

        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $msg->id);

        $this->assertSame(1, $conversa->mensagens()->where('remetente', 'bot')->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));
    }

    public function test_webhook_persiste_mensagem_imediatamente_antes_de_qualquer_job(): void
    {
        Queue::fake();

        $telefone = '5551966000004';

        $response = $this->withHeader('X-Webhook-Token', 'token-teste-debounce')
            ->postJson("/webhook/{$this->tenant->slug}", $this->payloadEvolution($telefone, 'Quero agendar', 'WH_DEB_1'));

        $response->assertStatus(200);

        // Nenhum job rodou (Queue fakeada), mas a mensagem já está no banco
        $this->assertDatabaseHas('mensagens', [
            'evolution_message_id' => 'WH_DEB_1',
            'remetente' => 'cliente',
            'conteudo' => 'Quero agendar',
        ]);
        $this->assertDatabaseHas('conversas', [
            'tenant_id' => $this->tenant->id,
            'telefone_cliente' => $telefone,
        ]);

        Queue::assertPushed(ProcessarMensagemWhatsapp::class, 1);
    }

    public function test_webhook_deduplica_mesmo_evolution_message_id(): void
    {
        Queue::fake();

        $telefone = '5551966000005';
        $payload = $this->payloadEvolution($telefone, 'Mensagem repetida', 'WH_DEB_DUP');

        $this->withHeader('X-Webhook-Token', 'token-teste-debounce')
            ->postJson("/webhook/{$this->tenant->slug}", $payload)
            ->assertStatus(200);

        $this->withHeader('X-Webhook-Token', 'token-teste-debounce')
            ->postJson("/webhook/{$this->tenant->slug}", $payload)
            ->assertStatus(200);

        $this->assertSame(1, Mensagem::where('evolution_message_id', 'WH_DEB_DUP')->count());
        Queue::assertPushed(ProcessarMensagemWhatsapp::class, 1);
    }

    public function test_webhook_despacha_job_com_delay_quando_debounce_configurado(): void
    {
        Queue::fake();
        config(['bot.debounce_seconds' => 5]);

        $this->withHeader('X-Webhook-Token', 'token-teste-debounce')
            ->postJson("/webhook/{$this->tenant->slug}", $this->payloadEvolution('5551966000006', 'oi', 'WH_DEB_DELAY'))
            ->assertStatus(200);

        Queue::assertPushed(ProcessarMensagemWhatsapp::class, fn ($job) => $job->delay !== null);
    }

    public function test_webhook_despacha_job_sem_delay_quando_debounce_desabilitado(): void
    {
        Queue::fake();
        config(['bot.debounce_seconds' => 0]);

        $this->withHeader('X-Webhook-Token', 'token-teste-debounce')
            ->postJson("/webhook/{$this->tenant->slug}", $this->payloadEvolution('5551966000007', 'oi', 'WH_DEB_NODELAY'))
            ->assertStatus(200);

        Queue::assertPushed(ProcessarMensagemWhatsapp::class, fn ($job) => $job->delay === null);
    }

    private function payloadEvolution(string $telefone, string $text, string $messageId): array
    {
        return [
            'event' => 'messages.upsert',
            'data' => [
                'key' => [
                    'remoteJid' => "{$telefone}@s.whatsapp.net",
                    'fromMe' => false,
                    'id' => $messageId,
                ],
                'pushName' => 'Cliente Teste',
                'messageType' => 'conversation',
                'message' => [
                    'conversation' => $text,
                ],
            ],
        ];
    }
}
