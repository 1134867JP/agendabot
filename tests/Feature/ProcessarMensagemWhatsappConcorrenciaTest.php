<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Models\User;
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
     * Caminho rápido: se a mensagem já existe quando o job começa a rodar (ex: reprocessamento
     * de um job que falhou por outro motivo após já ter salvo a mensagem), o job não deve
     * duplicá-la nem lançar exceção.
     */
    public function test_job_ignora_mensagem_ja_existente_sem_lancar_excecao(): void
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

        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, 'Mensagem original', $evolutionId, 'Cliente Teste');

        $this->assertSame(1, Mensagem::where('evolution_message_id', $evolutionId)->count());
    }

    /**
     * Verifica que a violação de unique constraint do Postgres (evolution_message_id) é
     * corretamente reconhecida pelo helper usado no catch do job — é essa classificação que
     * permite tratar a corrida como "mensagem duplicada" em vez de deixar a exceção propagar
     * e derrubar o worker.
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

        $job     = new ProcessarMensagemWhatsapp($this->tenant, $telefone, 'x');
        $metodo  = new \ReflectionMethod($job, 'isUniqueViolation');
        $metodo->setAccessible(true);

        $this->assertTrue($metodo->invoke($job, $excecao));
    }
}
