<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookChatsContatosUpsertTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome'                => 'Barbearia Chats',
            'slug'                => 'barbearia-chats',
            'tipo_servico'        => 'barbeiro',
            'ativo'               => true,
            'bot_ativo'           => true,
            'whatsapp_conectado'  => true,
            'webhook_token'       => 'token-teste-chats',
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    public function test_chats_upsert_cria_conversa_e_cliente_sem_disparar_processamento_de_mensagem(): void
    {
        Queue::fake();

        $telefone = '5551933333333';

        $response = $this->withHeader('X-Webhook-Token', 'token-teste-chats')->postJson("/webhook/{$this->tenant->slug}", [
            'event' => 'chats.upsert',
            'data'  => [[
                'remoteJid' => "{$telefone}@s.whatsapp.net",
                'pushName'  => 'Novo Contato',
            ]],
        ]);

        $response->assertStatus(200);
        Queue::assertNothingPushed();

        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => 'Novo Contato',
        ]);
        $this->assertDatabaseHas('conversas', [
            'tenant_id'        => $this->tenant->id,
            'telefone_cliente' => $telefone,
        ]);
    }

    public function test_chats_upsert_ignora_pushname_de_lastmessage_fromme(): void
    {
        Queue::fake();

        $telefone = '5551933333334';

        $response = $this->withHeader('X-Webhook-Token', 'token-teste-chats')->postJson("/webhook/{$this->tenant->slug}", [
            'event' => 'chats.upsert',
            'data'  => [[
                'remoteJid'   => "{$telefone}@s.whatsapp.net",
                'lastMessage' => [
                    'key'      => ['fromMe' => true],
                    'pushName' => 'Você',
                ],
            ]],
        ]);

        $response->assertStatus(200);

        $cliente = Cliente::where('tenant_id', $this->tenant->id)->where('telefone', $telefone)->first();
        $this->assertNotNull($cliente);
        $this->assertSame($telefone, $cliente->nome);
    }

    public function test_contacts_upsert_atualiza_nome_de_cliente_com_placeholder(): void
    {
        Queue::fake();

        $telefone = '5551933333335';
        Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => 'Cliente WhatsApp',
        ]);

        $response = $this->withHeader('X-Webhook-Token', 'token-teste-chats')->postJson("/webhook/{$this->tenant->slug}", [
            'event' => 'contacts.upsert',
            'data'  => [[
                'remoteJid' => "{$telefone}@s.whatsapp.net",
                'pushName'  => 'Contato Real',
            ]],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => 'Contato Real',
        ]);
    }

    public function test_contacts_upsert_nao_sobrescreve_nome_ja_valido(): void
    {
        Queue::fake();

        $telefone = '5551933333336';
        Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => 'Nome Já Correto',
        ]);

        $this->withHeader('X-Webhook-Token', 'token-teste-chats')->postJson("/webhook/{$this->tenant->slug}", [
            'event' => 'contacts.upsert',
            'data'  => [[
                'remoteJid' => "{$telefone}@s.whatsapp.net",
                'pushName'  => 'Outro Nome',
            ]],
        ]);

        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => 'Nome Já Correto',
        ]);
    }
}
