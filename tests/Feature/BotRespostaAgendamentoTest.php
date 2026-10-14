<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Agendamento;
use App\Models\Conversa;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Services\ConversaSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BotRespostaAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $profissional;

    private Servico $servico;

    private string $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Resposta',
            'slug' => 'barbearia-resposta',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'bot_ativo' => true,
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $this->servico = Servico::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Corte',
            'valor_min' => 40,
            'valor_max' => 40,
            'duracao_minutos' => 30,
            'ativo' => true,
        ]);

        $this->profissional = Profissional::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Asafe',
            'ativo' => true,
        ]);
        $this->profissional->servicos()->attach($this->servico->id);
        for ($dia = 0; $dia <= 6; $dia++) {
            $this->profissional->horarios()->create([
                'dia_semana' => $dia,
                'hora_inicio' => '09:00',
                'hora_fim' => '18:00',
                'duracao_slot' => 30,
            ]);
        }

        $this->data = Carbon::now($this->tenant->resolvedTimezone())->addDays(2)->format('Y-m-d');
    }

    public function test_agendamento_pelo_bot_usa_o_nome_informado_pelo_cliente(): void
    {
        $this->fakeIaReservaSeguidaDe('Agendado, Carlos! Te esperamos. ✂️');

        $this->clienteEnvia('5551966000001', 'Carlos Silva', 'Cliente Simulado');

        $this->assertSame('Carlos Silva', Agendamento::where('tenant_id', $this->tenant->id)->value('cliente_nome'));
    }

    #[DataProvider('nomesGenericos')]
    public function test_nome_generico_da_ia_mantem_o_nome_do_cadastro(string $nomeDaIa): void
    {
        $this->fakeIaReservaSeguidaDe('Agendado! ✂️', clienteNome: $nomeDaIa);

        $this->clienteEnvia('5551966000002', 'pode ser', 'Maria Souza');

        $this->assertSame('Maria Souza', Agendamento::where('tenant_id', $this->tenant->id)->value('cliente_nome'));
    }

    public static function nomesGenericos(): array
    {
        return [
            'vazio' => ['   '],
            'cliente' => ['Cliente'],
            'cliente whatsapp' => ['cliente WhatsApp'],
            'não informado' => ['Não informado'],
            'telefone' => ['+55 51 96600-0002'],
        ];
    }

    public function test_resposta_degenerada_apos_reserva_vira_confirmacao_com_os_dados_reais(): void
    {
        $this->fakeIaReservaSeguidaDe("Agendamento confirmado! 🎉 Seu corte\u{00A0}\u{200B}\u{00A0}\u{00A0}\u{200B}\u{00A0}\n\n\n\n");

        $conversa = $this->clienteEnvia('5551966000003', 'Carlos Silva', 'Carlos');

        $resposta = $this->ultimaRespostaDoBot($conversa);
        $dataBr = Carbon::parse($this->data)->format('d/m');
        $this->assertStringContainsString("{$dataBr} às 10:00", $resposta);
        $this->assertStringContainsString('Asafe', $resposta);
        $this->assertStringContainsString('Corte', $resposta);
        $this->assertDoesNotMatchRegularExpression('/[\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', $resposta);
    }

    public function test_resposta_degenerada_sem_acao_concluida_usa_mensagem_padrao(): void
    {
        $this->fakeIaTexto("Ok\u{200B}\u{00A0}\u{200B}\u{00A0}\u{200B}\u{00A0}");

        $conversa = $this->clienteEnvia('5551966000004', 'oi', 'Carlos');

        $this->assertSame(
            'Desculpe, não consegui processar sua mensagem. Pode repetir, por favor?',
            $this->ultimaRespostaDoBot($conversa),
        );
    }

    public function test_resposta_em_markdown_e_ajustada_para_o_whatsapp(): void
    {
        $this->fakeIaTexto("\n\nTemos **10:30** e __11:00__ com o Asafe.   \n\n\n\nQual prefere?\n");

        $conversa = $this->clienteEnvia('5551966000005', 'tem horário amanhã?', 'Carlos');

        $this->assertSame("Temos *10:30* e _11:00_ com o Asafe.\n\nQual prefere?", $this->ultimaRespostaDoBot($conversa));
    }

    private function fakeIaReservaSeguidaDe(string $textoFinal, string $clienteNome = 'Carlos Silva'): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [[
                        'type' => 'tool_use',
                        'id' => 'toolu_reserva',
                        'name' => 'criar_agendamento',
                        'input' => [
                            'cliente_nome' => $clienteNome,
                            'profissional_id' => $this->profissional->id,
                            'servico_ids' => [$this->servico->id],
                            'data' => $this->data,
                            'horario' => '10:00',
                        ],
                    ]],
                    'stop_reason' => 'tool_use',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ])
                ->push([
                    'content' => [['type' => 'text', 'text' => $textoFinal]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);
    }

    private function fakeIaTexto(string $texto): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => $texto]],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);
    }

    private function clienteEnvia(string $telefone, string $texto, string $pushName): Conversa
    {
        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $this->tenant, $telefone, $texto, 'MSG_'.$telefone, $pushName
        );
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $mensagem->id);

        return Conversa::where('tenant_id', $this->tenant->id)->where('telefone_cliente', $telefone)->firstOrFail();
    }

    private function ultimaRespostaDoBot(Conversa $conversa): string
    {
        return (string) $conversa->mensagens()->where('remetente', 'bot')->latest('id')->value('conteudo');
    }
}
