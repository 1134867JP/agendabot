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

    public function test_bloco_pendente_mostra_data_e_hora_legiveis(): void
    {
        $agendamento = $this->agendamentoPendente('5551966000010');
        $this->fakeIaTexto('Combinado!');

        $this->clienteEnvia('5551966000010', 'obrigado', 'Carlos');

        $sistema = collect($this->requisicoesIa()[0]['system'])->pluck('text')->join("\n");
        $dataBr = Carbon::parse($this->data)->format('d/m/Y');
        $this->assertStringContainsString("#{$agendamento->id} — {$dataBr} às 15:00", $sistema);
    }

    public function test_buscar_slots_avisa_que_o_horario_ocupado_e_do_proprio_cliente(): void
    {
        // Cliente responde "sim" a um agendamento já feito e o modelo busca horários:
        // sem o aviso, o horário dele aparece ocupado e o bot dizia "15:00 indisponível".
        $agendamento = $this->agendamentoPendente('5551966000011');
        $this->fakeIaFerramentaSeguidaDe('buscar_slots', ['servico_ids' => [$this->servico->id]], 'Seu horário das 15:00 está mantido.');

        $this->clienteEnvia('5551966000011', 'Sim, pode confirmar', 'Carlos');

        $resultado = $this->resultadoDaFerramentaNaSegundaChamada();
        $dataBr = Carbon::parse($this->data)->format('d/m');
        $this->assertStringContainsString("#{$agendamento->id}", $resultado['agendamentos_do_cliente']);
        $this->assertStringContainsString("{$dataBr} às 15:00", $resultado['agendamentos_do_cliente']);
        $this->assertArrayHasKey('slots', $resultado);
    }

    public function test_data_invalida_volta_para_a_ia_com_o_formato_esperado(): void
    {
        // Antes virava erro_tecnico e o bot respondia "deu um erro no sistema".
        $this->fakeIaFerramentaSeguidaDe('criar_agendamento', [
            'cliente_nome' => 'Carlos Silva',
            'profissional_id' => $this->profissional->id,
            'servico_ids' => [$this->servico->id],
            'data' => 'amanhã',
            'horario' => '10:00',
        ], 'Só um instante.');

        $this->clienteEnvia('5551966000013', 'Pode marcar amanhã às 10h', 'Carlos');

        $resultado = $this->resultadoDaFerramentaNaSegundaChamada();
        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('dados_invalidos', $resultado['erro']);
        $this->assertStringContainsString('AAAA-MM-DD', $resultado['mensagem']);
    }

    public function test_listar_agendamentos_do_cliente_mostra_data_e_hora_legiveis(): void
    {
        $agendamento = $this->agendamentoPendente('5551966000012');
        $this->fakeIaFerramentaSeguidaDe('listar_agendamentos_cliente', [], 'Você tem um corte marcado.');

        $this->clienteEnvia('5551966000012', 'quais horários eu tenho?', 'Carlos');

        $resultado = $this->resultadoDaFerramentaNaSegundaChamada();
        $dataBr = Carbon::parse($this->data)->format('d/m');
        $this->assertStringContainsString("ID #{$agendamento->id}", $resultado['agendamentos']);
        $this->assertStringContainsString("{$dataBr} às 15:00", $resultado['agendamentos']);
        $this->assertStringNotContainsString('à00', $resultado['agendamentos']);
    }

    private function agendamentoPendente(string $telefone): Agendamento
    {
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'telefone' => $telefone, 'nome' => 'Carlos']);
        $inicio = Carbon::parse("{$this->data} 15:00", $this->tenant->resolvedTimezone());

        return Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'profissional_id' => $this->profissional->id,
            'servico_id' => $this->servico->id,
            'cliente_id' => $cliente->id,
            'cliente_nome' => $cliente->nome,
            'cliente_telefone' => $telefone,
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'data_hora' => $inicio,
            'duracao_minutos' => 30,
            'status' => 'agendado',
            'origem' => 'bot',
        ]);
    }

    private function fakeIaFerramentaSeguidaDe(string $ferramenta, array $input, string $textoFinal): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => $ferramenta, 'input' => $input]],
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

    /** @return list<array> corpos das requisições enviadas à IA, em ordem */
    private function requisicoesIa(): array
    {
        return Http::recorded(fn ($request) => str_contains($request->url(), 'anthropic.com'))
            ->map(fn ($par) => $par[0]->data())
            ->values()
            ->all();
    }

    private function resultadoDaFerramentaNaSegundaChamada(): array
    {
        $segunda = $this->requisicoesIa()[1];
        $toolResult = collect(end($segunda['messages'])['content'])->firstWhere('type', 'tool_result');

        return json_decode($toolResult['content'], true);
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
