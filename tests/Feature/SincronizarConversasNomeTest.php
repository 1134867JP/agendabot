<?php

namespace Tests\Feature;

use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Cliente;
use App\Models\Conversa;
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

    public function test_sincronizacao_vincula_numero_sem_nono_digito_ao_cliente_ja_salvo(): void
    {
        $telefoneSalvo = '5554996281785';
        $telefoneWhatsapp = '555496281785';

        $clienteSalvo = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone' => $telefoneSalvo,
            'nome' => 'Carla Odontologia',
        ]);

        $this->mockEvolution([
            'contatos' => [],
            'chats' => [['remoteJid' => "{$telefoneWhatsapp}@s.whatsapp.net"]],
            'mensagens' => [[
                'key' => ['id' => 'MSG-NONO-DIGITO', 'fromMe' => false],
                'messageType' => 'conversation',
                'message' => ['conversation' => 'Quero marcar uma avaliação'],
                'messageTimestamp' => now()->timestamp,
            ]],
        ]);

        (new SincronizarConversasWhatsappJob($this->tenant))->handle(
            app(EvolutionApiService::class),
            app(\App\Services\ConversaSyncService::class)
        );

        $conversa = Conversa::where('tenant_id', $this->tenant->id)
            ->where('telefone_cliente', $telefoneWhatsapp)
            ->firstOrFail();

        $this->assertSame($clienteSalvo->id, $conversa->cliente_id);
        $this->assertSame('Carla Odontologia', $conversa->cliente->nome);
        $this->assertDatabaseMissing('clientes', [
            'tenant_id' => $this->tenant->id,
            'telefone' => $telefoneWhatsapp,
        ]);
    }

    public function test_sincronizacao_prioriza_nome_salvo_do_contato_sobre_pushname(): void
    {
        $telefone = '5551987654321';

        $this->mockEvolution([
            'contatos' => [[
                'remoteJid' => "{$telefone}@s.whatsapp.net",
                'name' => 'Roberto Clínica',
                'pushName' => 'Beto',
            ]],
            'chats' => [['remoteJid' => "{$telefone}@s.whatsapp.net"]],
            'mensagens' => [[
                'key' => ['id' => 'MSG-CONTATO-SALVO', 'fromMe' => false],
                'pushName' => 'Beto',
                'messageType' => 'conversation',
                'message' => ['conversation' => 'Olá'],
                'messageTimestamp' => now()->timestamp,
            ]],
        ]);

        (new SincronizarConversasWhatsappJob($this->tenant))->handle(
            app(EvolutionApiService::class),
            app(\App\Services\ConversaSyncService::class)
        );

        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $this->tenant->id,
            'telefone' => $telefone,
            'nome' => 'Roberto Clínica',
        ]);
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
