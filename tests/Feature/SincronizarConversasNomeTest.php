<?php

namespace Tests\Feature;

use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EvolutionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SincronizarConversasNomeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome'                => 'Barbearia Sync',
            'slug'                => 'barbearia-sync',
            'tipo_servico'        => 'barbeiro',
            'ativo'               => true,
            'evolution_instance'  => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    public function test_sincronizacao_ignora_pushname_de_mensagem_fromme_ao_extrair_nome(): void
    {
        $telefone = '5551999999999';

        $this->mockEvolution([
            'contatos' => [],
            'chats'    => [[
                'remoteJid' => "{$telefone}@s.whatsapp.net",
            ]],
            'mensagens' => [
                // mensagem mais recente: enviada pelo próprio atendente, pushName = "Você"
                [
                    'key'              => ['id' => 'MSG1', 'fromMe' => true],
                    'pushName'         => 'Você',
                    'messageType'      => 'conversation',
                    'message'          => ['conversation' => 'Já te atendo!'],
                    'messageTimestamp' => now()->subMinutes(1)->timestamp,
                ],
                // mensagem mais antiga: enviada pelo cliente, com o nome real
                [
                    'key'              => ['id' => 'MSG2', 'fromMe' => false],
                    'pushName'         => 'João Cliente',
                    'messageType'      => 'conversation',
                    'message'          => ['conversation' => 'Quero agendar um corte'],
                    'messageTimestamp' => now()->subMinutes(5)->timestamp,
                ],
            ],
        ]);

        (new SincronizarConversasWhatsappJob($this->tenant))->handle(
            app(EvolutionApiService::class),
            app(\App\Services\ConversaSyncService::class)
        );

        $cliente = Cliente::where('tenant_id', $this->tenant->id)->where('telefone', $telefone)->first();

        $this->assertNotNull($cliente);
        $this->assertSame('João Cliente', $cliente->nome);
    }

    public function test_sincronizacao_nao_sobrescreve_nome_valido_por_placeholder(): void
    {
        $telefone = '5551988888888';

        Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => 'Maria Real',
        ]);

        $this->mockEvolution([
            'contatos'  => [],
            'chats'     => [['remoteJid' => "{$telefone}@s.whatsapp.net"]],
            'mensagens' => [[
                'key'              => ['id' => 'MSG3', 'fromMe' => true],
                'pushName'         => 'Você',
                'messageType'      => 'conversation',
                'message'          => ['conversation' => 'Olá!'],
                'messageTimestamp' => now()->timestamp,
            ]],
        ]);

        (new SincronizarConversasWhatsappJob($this->tenant))->handle(
            app(EvolutionApiService::class),
            app(\App\Services\ConversaSyncService::class)
        );

        $cliente = Cliente::where('tenant_id', $this->tenant->id)->where('telefone', $telefone)->first();

        $this->assertSame('Maria Real', $cliente->nome);
    }

    private function mockEvolution(array $dados): void
    {
        $this->mock(EvolutionApiService::class, function ($mock) use ($dados) {
            $mock->shouldReceive('fetchContacts')->andReturn($dados['contatos']);
            $mock->shouldReceive('fetchChats')->andReturn($dados['chats']);
            $mock->shouldReceive('fetchMessages')->andReturn($dados['mensagens']);
        });
    }
}
