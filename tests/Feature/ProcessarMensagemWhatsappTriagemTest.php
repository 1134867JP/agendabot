<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessarMensagemWhatsappTriagemTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Triagem Job',
            'slug' => 'barbearia-triagem-job',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'bot_ativo' => true,
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'configuracoes' => [
                'triagem' => [
                    'palavras_chave_humano' => ['atendente', 'humano'],
                    'max_tentativas_sem_entender' => 2,
                    'transferir_fora_do_horario' => false,
                    'mensagem_transferencia' => 'Já vou te transferir!',
                ],
            ],
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    public function test_transfere_para_humano_sem_chamar_claude_quando_mensagem_contem_palavra_chave(): void
    {
        Http::fake();

        $telefone = '5551977000001';

        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $this->tenant, $telefone, 'Quero falar com um atendente, por favor', 'MSG_TRIAGEM_1', 'Cliente Teste'
        );
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $mensagem->id);

        $conversa = Conversa::where('tenant_id', $this->tenant->id)->where('telefone_cliente', $telefone)->first();

        $this->assertNotNull($conversa);
        $this->assertSame('aguardando_humano', $conversa->status_v2);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));

        $this->assertDatabaseHas('mensagens', [
            'conversa_id' => $conversa->id,
            'remetente' => 'bot',
            'conteudo' => 'Já vou te transferir!',
        ]);
    }

    public function test_nao_transfere_quando_mensagem_nao_contem_palavra_chave(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Olá! Como posso ajudar?']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        $telefone = '5551977000002';

        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $this->tenant, $telefone, 'Oi, queria agendar um corte', 'MSG_TRIAGEM_2', 'Cliente Teste'
        );
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $mensagem->id);

        $conversa = Conversa::where('tenant_id', $this->tenant->id)->where('telefone_cliente', $telefone)->first();

        $this->assertNotNull($conversa);
        $this->assertNotSame('aguardando_humano', $conversa->status_v2);
    }
}
