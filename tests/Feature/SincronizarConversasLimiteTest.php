<?php

namespace Tests\Feature;

use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversaSyncService;
use App\Services\EvolutionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    public function test_sincroniza_no_maximo_30_conversas_priorizando_as_mais_recentes(): void
    {
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
            $mock->shouldReceive('fetchMessages')->andReturn([]);
        });

        (new SincronizarConversasWhatsappJob($this->tenant))->handle(
            app(EvolutionApiService::class),
            app(ConversaSyncService::class)
        );

        $this->assertSame(30, Cliente::where('tenant_id', $this->tenant->id)->count());

        // Os 30 mais recentes (índices 0–29) devem ter sido sincronizados...
        for ($i = 0; $i < 30; $i++) {
            $this->assertDatabaseHas('clientes', [
                'tenant_id' => $this->tenant->id,
                'telefone'  => "55519990000{$i}",
            ]);
        }

        // ...e os 10 mais antigos (índices 30–39) devem ter ficado de fora.
        for ($i = 30; $i < 40; $i++) {
            $this->assertDatabaseMissing('clientes', [
                'tenant_id' => $this->tenant->id,
                'telefone'  => "55519990000{$i}",
            ]);
        }
    }

    public function test_nao_cria_clientes_ou_conversas_para_chats_sem_mensagens(): void
    {
        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('fetchContacts')->andReturn([]);
            $mock->shouldReceive('fetchChats')->andReturn([
                ['remoteJid' => '5551999999999@s.whatsapp.net'],
            ]);
            $mock->shouldReceive('fetchMessages')->andReturn([]);
        });

        (new SincronizarConversasWhatsappJob($this->tenant))->handle(
            app(EvolutionApiService::class),
            app(ConversaSyncService::class),
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
}
