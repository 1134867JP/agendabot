<?php

namespace Tests\Feature;

use App\Jobs\SincronizarConversasWhatsappJob;
use App\Jobs\SincronizarConversasWhatsappLoteJob;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversaSyncService;
use App\Services\EvolutionApiService;
use App\Services\WhatsAppSyncState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SincronizarConversasLimiteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome'                => 'Barbearia Limite Sync',
            'slug'                => 'barbearia-limite-sync',
            'tipo_servico'        => 'barbeiro',
            'ativo'               => true,
            'evolution_instance'  => 'instancia-teste',
            'whatsapp_conectado'  => true,
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    public function test_sincroniza_todas_as_conversas_em_lotes(): void
    {
        Bus::fake();
        $syncState = app(WhatsAppSyncState::class);
        $executionId = $syncState->iniciar($this->tenant);

        // 40 chats, do mais recente (índice 0) ao mais antigo (índice 39)
        $chats = [];
        for ($i = 0; $i < 40; $i++) {
            $chats[] = [
                'remoteJid' => "55519990000{$i}@s.whatsapp.net",
                'updatedAt' => now()->subMinutes($i)->toIso8601String(),
                'lastMessage' => [
                    'key' => ['id' => "MSG-LIMITE-{$i}", 'fromMe' => false],
                    'messageType' => 'conversation',
                    'message' => ['conversation' => "Mensagem {$i}"],
                    'messageTimestamp' => now()->subMinutes($i)->timestamp,
                ],
            ];
        }

        $this->mock(EvolutionApiService::class, function ($mock) use ($chats) {
            $mock->shouldReceive('fetchContacts')->andReturn([]);
            $mock->shouldReceive('fetchChats')->andReturn($chats);
            $mock->shouldReceive('fetchMessages')->times(40)->andReturn([]);
        });

        (new SincronizarConversasWhatsappJob($this->tenant, $executionId))->handle(
            app(EvolutionApiService::class),
            app(ConversaSyncService::class),
            $syncState,
        );

        Bus::assertChained([
            SincronizarConversasWhatsappLoteJob::class,
            SincronizarConversasWhatsappLoteJob::class,
            SincronizarConversasWhatsappLoteJob::class,
            SincronizarConversasWhatsappLoteJob::class,
        ]);

        foreach (array_chunk($chats, 10) as $indice => $lote) {
            (new SincronizarConversasWhatsappLoteJob(
                $this->tenant,
                $lote,
                $indice * 10,
                40,
                $indice === 3,
                $executionId,
            ))->handle(
                app(EvolutionApiService::class),
                app(ConversaSyncService::class),
                $syncState,
            );
        }

        $this->assertSame(40, Cliente::where('tenant_id', $this->tenant->id)->count());

        for ($i = 0; $i < 40; $i++) {
            $this->assertDatabaseHas('clientes', [
                'tenant_id' => $this->tenant->id,
                'telefone'  => "55519990000{$i}",
            ]);
        }

        $status = Cache::get("sync_whatsapp_tenant_{$this->tenant->id}");
        $this->assertSame('completed', $status['status']);
        $this->assertSame(40, $status['processed']);
        $this->assertSame(40, $status['total']);
    }

    public function test_nao_cria_clientes_ou_conversas_para_chats_sem_mensagens(): void
    {
        Bus::fake();
        $syncState = app(WhatsAppSyncState::class);
        $executionId = $syncState->iniciar($this->tenant);

        $chat = ['remoteJid' => '5551999999999@s.whatsapp.net'];

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('fetchContacts')->andReturn([]);
            $mock->shouldReceive('fetchChats')->andReturn([
                ['remoteJid' => '5551999999999@s.whatsapp.net'],
            ]);
            $mock->shouldReceive('fetchMessages')->andReturn([]);
        });

        (new SincronizarConversasWhatsappJob($this->tenant, $executionId))->handle(
            app(EvolutionApiService::class),
            app(ConversaSyncService::class),
            $syncState,
        );

        (new SincronizarConversasWhatsappLoteJob(
            $this->tenant,
            [$chat],
            0,
            1,
            true,
            $executionId,
        ))->handle(
            app(EvolutionApiService::class),
            app(ConversaSyncService::class),
            $syncState,
        );

        $this->assertDatabaseMissing('clientes', [
            'tenant_id' => $this->tenant->id,
            'telefone' => '5551999999999',
        ]);
        $this->assertDatabaseCount('conversas', 0);
    }

    public function test_resolve_numero_real_quando_whatsapp_envia_identificador_lid(): void
    {
        $sync = app(ConversaSyncService::class);

        $this->assertSame(
            '5551999999999',
            $sync->resolverTelefoneMensagem([
                'key' => [
                    'remoteJid' => '123456789012345@lid',
                    'remoteJidAlt' => '5551999999999@s.whatsapp.net',
                ],
            ]),
        );

        $this->assertNull($sync->resolverTelefoneMensagem([
            'key' => ['remoteJid' => '123456789012345@lid'],
        ]));
    }

    public function test_chatsrecenteslimitados_ordena_do_mais_recente_para_o_mais_antigo(): void
    {
        $sync = app(ConversaSyncService::class);

        $chats = [
            ['remoteJid' => 'antigo@s.whatsapp.net',      'updatedAt' => now()->subDays(5)->toIso8601String()],
            ['remoteJid' => 'recente@s.whatsapp.net',     'updatedAt' => now()->subMinutes(1)->toIso8601String()],
            ['remoteJid' => 'intermediario@s.whatsapp.net', 'updatedAt' => now()->subHours(2)->toIso8601String()],
        ];

        $ordenados = $sync->chatsRecentesLimitados($chats, 30);

        $this->assertSame(
            ['recente@s.whatsapp.net', 'intermediario@s.whatsapp.net', 'antigo@s.whatsapp.net'],
            array_column($ordenados, 'remoteJid')
        );
    }

    public function test_cancelamento_impede_lote_pendente_de_processar_conversas(): void
    {
        $syncState = app(WhatsAppSyncState::class);
        $executionId = $syncState->iniciar($this->tenant);
        $syncState->atualizar($this->tenant, $executionId, [
            'status' => 'running',
            'processed' => 3,
            'total' => 10,
        ]);
        $status = $syncState->cancelar($this->tenant);

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldNotReceive('fetchContacts');
            $mock->shouldNotReceive('fetchMessages');
        });

        (new SincronizarConversasWhatsappLoteJob(
            $this->tenant,
            [['remoteJid' => '5551999999999@s.whatsapp.net']],
            3,
            10,
            false,
            $executionId,
        ))->handle(
            app(EvolutionApiService::class),
            app(ConversaSyncService::class),
            $syncState,
        );

        $this->assertSame('cancelled', $status['status']);
        $this->assertSame(3, $status['processed']);
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_execucao_antiga_nao_sobrescreve_nova_sincronizacao(): void
    {
        $syncState = app(WhatsAppSyncState::class);
        $executionIdAntigo = $syncState->iniciar($this->tenant);
        $syncState->cancelar($this->tenant);
        $executionIdNovo = $syncState->iniciar($this->tenant);

        $atualizou = $syncState->atualizar($this->tenant, $executionIdAntigo, [
            'status' => 'completed',
            'processed' => 10,
        ]);

        $status = $syncState->status($this->tenant);
        $this->assertFalse($atualizou);
        $this->assertSame($executionIdNovo, $status['execution_id']);
        $this->assertSame('queued', $status['status']);
    }

    public function test_perda_de_conexao_impede_job_de_iniciar(): void
    {
        $syncState = app(WhatsAppSyncState::class);
        $executionId = $syncState->iniciar($this->tenant);
        $this->tenant->update(['whatsapp_conectado' => false]);

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldNotReceive('fetchContacts');
            $mock->shouldNotReceive('fetchChats');
        });

        (new SincronizarConversasWhatsappJob($this->tenant, $executionId))->handle(
            app(EvolutionApiService::class),
            app(ConversaSyncService::class),
            $syncState,
        );

        $this->assertSame('cancelled', $syncState->status($this->tenant)['status']);
    }
}
