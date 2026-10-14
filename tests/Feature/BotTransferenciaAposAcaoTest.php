<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Services\ConversaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * No modo agendamento, o modelo às vezes pede a transferência junto com a ação que
 * o cliente pediu. Se a ação foi gravada e o cliente não pediu uma pessoa, ele recebe
 * o resultado real e a conversa segue com o bot.
 */
class BotTransferenciaAposAcaoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $asafe;

    private Servico $corte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Transferência',
            'slug' => 'barbearia-transferencia',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'bot_ativo' => true,
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->corte = Servico::create(['tenant_id' => $this->tenant->id, 'nome' => 'Corte', 'duracao_minutos' => 30, 'ativo' => true]);
        $this->asafe = Profissional::create(['tenant_id' => $this->tenant->id, 'nome' => 'Asafe', 'ativo' => true]);
        $this->asafe->servicos()->attach($this->corte->id);
    }

    public function test_cancelamento_com_transferencia_nao_pedida_responde_o_resultado_e_segue_no_bot(): void
    {
        $agendamento = $this->agendamentoFuturo('5551933000001');
        $this->fakeIaChamando([
            ['name' => 'cancelar_agendamento', 'input' => []],
            ['name' => 'transferir_para_humano', 'input' => []],
        ]);

        $conversa = $this->clienteEnvia('5551933000001', 'preciso cancelar meu horário de quinta');

        $this->assertSame('cancelado', $agendamento->fresh()->status);
        $this->assertSame('ativa', $conversa->status_v2);
        $this->assertStringContainsString('foi cancelado', $this->ultimaRespostaDoBot($conversa));
        $this->assertSame(1, $this->chamadasAIa());
    }

    public function test_transferencia_pedida_antes_do_cancelamento_nao_impede_o_cancelamento(): void
    {
        $agendamento = $this->agendamentoFuturo('5551933000002');
        $this->fakeIaChamando([
            ['name' => 'transferir_para_humano', 'input' => []],
            ['name' => 'cancelar_agendamento', 'input' => []],
        ]);

        $conversa = $this->clienteEnvia('5551933000002', 'preciso cancelar meu horário de quinta');

        $this->assertSame('cancelado', $agendamento->fresh()->status);
        $this->assertSame('ativa', $conversa->status_v2);
        $this->assertStringContainsString('foi cancelado', $this->ultimaRespostaDoBot($conversa));
        $this->assertSame(1, $this->chamadasAIa());
    }

    public function test_cliente_que_pede_atendente_recebe_o_resultado_antes_da_transferencia(): void
    {
        $agendamento = $this->agendamentoFuturo('5551933000003');
        $this->fakeIaChamando([
            ['name' => 'transferir_para_humano', 'input' => []],
            ['name' => 'cancelar_agendamento', 'input' => []],
        ]);

        $conversa = $this->clienteEnvia('5551933000003', 'cancela meu horário e quero falar com um atendente');

        $this->assertSame('cancelado', $agendamento->fresh()->status);
        $this->assertSame('aguardando_humano', $conversa->status_v2);
        $resposta = $this->ultimaRespostaDoBot($conversa);
        $this->assertStringContainsString('foi cancelado', $resposta);
        $this->assertStringContainsString('atendente', $resposta);
        $this->assertLessThan(mb_strpos($resposta, 'atendente'), mb_strpos($resposta, 'foi cancelado'));
    }

    #[DataProvider('pedidosClarosDeAtendente')]
    public function test_pedido_claro_de_atendente_transfere(string $texto): void
    {
        $telefone = '55519330'.substr((string) crc32($texto), 0, 5);
        $this->agendamentoFuturo($telefone);
        $this->fakeIaChamando([
            ['name' => 'cancelar_agendamento', 'input' => []],
            ['name' => 'transferir_para_humano', 'input' => []],
        ]);

        $conversa = $this->clienteEnvia($telefone, $texto);

        $this->assertSame('aguardando_humano', $conversa->status_v2);
    }

    public static function pedidosClarosDeAtendente(): array
    {
        return [
            ['cancela e quero falar com uma pessoa'],
            ['cancela e me passa pra atendente'],
            ['cancela, preciso conversar com alguém'],
            ['cancela e chama um humano por favor'],
            ['cancela e quero atendimento humano'],
            ['cancela. Não quero falar com robô'],
        ];
    }

    #[DataProvider('mencoesSemPedidoDeAtendente')]
    public function test_mencao_a_pessoa_sem_pedido_claro_nao_transfere(string $texto): void
    {
        $telefone = '55519331'.substr((string) crc32($texto), 0, 5);
        $agendamento = $this->agendamentoFuturo($telefone);
        $this->fakeIaChamando([
            ['name' => 'cancelar_agendamento', 'input' => []],
            ['name' => 'transferir_para_humano', 'input' => []],
        ]);

        $conversa = $this->clienteEnvia($telefone, $texto);

        $this->assertSame('cancelado', $agendamento->fresh()->status);
        $this->assertSame('ativa', $conversa->status_v2);
    }

    public static function mencoesSemPedidoDeAtendente(): array
    {
        return [
            ['alguém pode cancelar pra mim?'],
            ['cancela, a pessoa que ia não pode mais'],
            ['cancela que a recepção já sabe'],
        ];
    }

    public function test_transfere_normalmente_quando_a_acao_falha(): void
    {
        $agendamento = $this->agendamentoFuturo('5551933000004');
        $this->fakeIaChamando([
            ['name' => 'reagendar_agendamento', 'input' => ['agendamento_id' => 999999, 'data' => '2030-01-01', 'hora' => '10:00']],
            ['name' => 'transferir_para_humano', 'input' => []],
        ]);

        $conversa = $this->clienteEnvia('5551933000004', 'quero trocar meu horário para outro dia');

        $this->assertSame('agendado', $agendamento->fresh()->status);
        $this->assertSame('aguardando_humano', $conversa->status_v2);
    }

    private function agendamentoFuturo(string $telefone): Agendamento
    {
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'telefone' => $telefone, 'nome' => 'Bruno']);
        $inicio = now()->addDays(2)->setTime(16, 0);

        return Agendamento::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $cliente->id, 'cliente_nome' => 'Bruno',
            'cliente_telefone' => $telefone, 'profissional_id' => $this->asafe->id, 'servico_id' => $this->corte->id,
            'data_hora' => $inicio, 'inicio' => $inicio, 'fim' => $inicio->copy()->addMinutes(30),
            'duracao_minutos' => 30, 'status' => 'agendado', 'origem' => 'bot',
        ]);
    }

    /** @param list<array{name: string, input: array}> $ferramentas chamadas numa única resposta do modelo */
    private function fakeIaChamando(array $ferramentas): void
    {
        $blocos = collect($ferramentas)->map(fn ($f, $i) => [
            'type' => 'tool_use', 'id' => 'toolu_'.$i, 'name' => $f['name'], 'input' => $f['input'] ?: new \stdClass,
        ])->all();

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => $blocos,
                    'stop_reason' => 'tool_use',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ])
                ->push([
                    'content' => [['type' => 'text', 'text' => 'Vou encaminhar para uma atendente.']],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);
    }

    private function clienteEnvia(string $telefone, string $texto): Conversa
    {
        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $this->tenant, $telefone, $texto, 'MSG_'.$telefone.'_'.md5($texto), 'Bruno'
        );
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $mensagem->id);

        return Conversa::where('tenant_id', $this->tenant->id)->where('telefone_cliente', $telefone)->firstOrFail();
    }

    private function ultimaRespostaDoBot(Conversa $conversa): string
    {
        return (string) $conversa->mensagens()->where('remetente', 'bot')->latest('id')->value('conteudo');
    }

    private function chamadasAIa(): int
    {
        return Http::recorded(fn ($request) => str_contains($request->url(), 'anthropic.com'))->count();
    }
}
