<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversaSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessarMensagemWhatsappConcorrenciaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome'                => 'Barbearia Concorrencia',
            'slug'                => 'barbearia-concorrencia',
            'tipo_servico'        => 'barbeiro',
            'ativo'               => true,
            'bot_ativo'           => true,
            'evolution_instance'  => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    /**
     * Dedup no ponto de persistência: se a mensagem já existe (ex: webhook reentregue
     * pela Evolution API), registrarMensagemRecebida não deve duplicá-la nem lançar
     * exceção — retorna null e nenhum job é despachado para ela.
     */
    public function test_persistencia_ignora_mensagem_ja_existente_sem_lancar_excecao(): void
    {
        $telefone    = '5551900000001';
        $evolutionId = 'JA_EXISTE_1';

        $cliente  = Cliente::create(['tenant_id' => $this->tenant->id, 'telefone' => $telefone, 'nome' => 'Cliente Teste']);
        $conversa = Conversa::create(['tenant_id' => $this->tenant->id, 'telefone_cliente' => $telefone, 'cliente_id' => $cliente->id, 'status_v2' => 'ativa']);
        $conversa->mensagens()->create([
            'remetente'            => 'cliente',
            'tipo'                 => 'texto',
            'conteudo'             => 'Mensagem original',
            'evolution_message_id' => $evolutionId,
            'enviada_em'           => now(),
        ]);

        $resultado = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $this->tenant, $telefone, 'Mensagem original', $evolutionId, 'Cliente Teste'
        );

        $this->assertNull($resultado);
        $this->assertSame(1, Mensagem::where('evolution_message_id', $evolutionId)->count());
    }

    /**
     * Retry defensivo: se a mensagem persistida foi removida antes do job rodar
     * (ex: limpeza de histórico), o job retorna silenciosamente sem lançar exceção.
     */
    public function test_job_retorna_sem_excecao_quando_mensagem_nao_existe_mais(): void
    {
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, '5551900000003', 999999);

        $this->assertSame(0, Mensagem::count());
    }

    /**
     * Verifica que a violação de unique constraint do Postgres (evolution_message_id) é
     * corretamente reconhecida pelo helper usado no catch da persistência — é essa
     * classificação que permite tratar a corrida como "mensagem duplicada" em vez de
     * deixar a exceção propagar.
     */
    public function test_isuniqueviolation_reconhece_violacao_real_de_unique_constraint(): void
    {
        $telefone    = '5551900000002';
        $evolutionId = 'RACE_MSG_2';

        $cliente  = Cliente::create(['tenant_id' => $this->tenant->id, 'telefone' => $telefone, 'nome' => 'Cliente Teste']);
        $conversa = Conversa::create(['tenant_id' => $this->tenant->id, 'telefone_cliente' => $telefone, 'cliente_id' => $cliente->id, 'status_v2' => 'ativa']);

        $conversa->mensagens()->create([
            'remetente'            => 'cliente',
            'tipo'                 => 'texto',
            'conteudo'             => 'Primeira inserção',
            'evolution_message_id' => $evolutionId,
            'enviada_em'           => now(),
        ]);

        $excecao = null;
        try {
            $conversa->mensagens()->create([
                'remetente'            => 'cliente',
                'tipo'                 => 'texto',
                'conteudo'             => 'Segunda inserção concorrente',
                'evolution_message_id' => $evolutionId,
                'enviada_em'           => now(),
            ]);
        } catch (QueryException $e) {
            $excecao = $e;
        }

        $this->assertNotNull($excecao, 'Esperava que a segunda inserção violasse a unique constraint.');

        $this->assertTrue(app(ConversaSyncService::class)->isUniqueViolation($excecao));
    }
}
