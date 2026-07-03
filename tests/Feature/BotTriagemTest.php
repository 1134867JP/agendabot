<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Services\ClaudeAgentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotTriagemTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'nome'                => 'Clínica Triagem',
            'slug'                => 'clinica-triagem',
            'tipo_servico'        => 'clinica',
            'ativo'               => true,
            'bot_ativo'           => true,
            'nome_agente'         => 'Bia',
            'tom_voz'             => 'semiformal',
            'modo_bot'            => 'triagem',
            'horario_atendimento' => [
                0 => ['ativo' => false, 'abertura' => '08:00', 'fechamento' => '18:00'],
                1 => ['ativo' => true,  'abertura' => '08:00', 'fechamento' => '18:00'],
                2 => ['ativo' => true,  'abertura' => '08:00', 'fechamento' => '18:00'],
                3 => ['ativo' => true,  'abertura' => '08:00', 'fechamento' => '18:00'],
                4 => ['ativo' => true,  'abertura' => '08:00', 'fechamento' => '18:00'],
                5 => ['ativo' => true,  'abertura' => '08:00', 'fechamento' => '18:00'],
                6 => ['ativo' => false, 'abertura' => '08:00', 'fechamento' => '18:00'],
            ],
        ]);
    }

    private function clienteInfo(): array
    {
        return ['id' => 1, 'nome' => 'Fulano', 'telefone' => '5551999999999'];
    }

    public function test_modo_triagem_expoe_apenas_ferramenta_de_transferencia(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content'     => [['type' => 'text', 'text' => 'Olá! Qual serviço você deseja? 😊']],
                'stop_reason' => 'end_turn',
                'usage'       => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
        ]);

        app(ClaudeAgentService::class)->processar(
            $this->tenant,
            [['role' => 'user', 'content' => 'oi']],
            $this->clienteInfo(),
        );

        Http::assertSent(function ($request) {
            $tools = collect($request->data()['tools'] ?? []);
            $nomes = $tools->pluck('name')->all();

            return $nomes === ['transferir_para_humano']
                && ! in_array('criar_agendamento', $nomes, true)
                && ! in_array('buscar_slots', $nomes, true);
        });
    }

    public function test_transferir_para_humano_marca_flag_e_nao_cria_agendamento(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content'     => [[
                        'type'  => 'tool_use',
                        'id'    => 'tool_1',
                        'name'  => 'transferir_para_humano',
                        'input' => ['resumo' => 'Limpeza, quarta de manhã', 'nome_cliente' => 'Fulano', 'preferencia' => 'quarta de manhã'],
                    ]],
                    'stop_reason' => 'tool_use',
                    'usage'       => ['input_tokens' => 20, 'output_tokens' => 8],
                ])
                ->push([
                    'content'     => [['type' => 'text', 'text' => 'Perfeito! Uma atendente vai te retornar. 🙂']],
                    'stop_reason' => 'end_turn',
                    'usage'       => ['input_tokens' => 5, 'output_tokens' => 5],
                ]),
        ]);

        $resultado = app(ClaudeAgentService::class)->processar(
            $this->tenant,
            [['role' => 'user', 'content' => 'quero marcar limpeza quarta de manhã, sou o Fulano']],
            $this->clienteInfo(),
        );

        $this->assertTrue($resultado['transferir']);
        $this->assertSame(0, Agendamento::count());
    }

    public function test_em_horario_atendimento_respeita_dia_e_faixa(): void
    {
        // Quarta-feira (dia ativo)
        $quartaManha = Carbon::create(2026, 7, 1, 10, 0, 0, 'America/Sao_Paulo'); // 2026-07-01 é quarta
        $quartaNoite = Carbon::create(2026, 7, 1, 20, 0, 0, 'America/Sao_Paulo');
        $domingo     = Carbon::create(2026, 7, 5, 10, 0, 0, 'America/Sao_Paulo'); // domingo (inativo)

        $this->assertTrue($this->tenant->emHorarioAtendimento($quartaManha));
        $this->assertFalse($this->tenant->emHorarioAtendimento($quartaNoite));
        $this->assertFalse($this->tenant->emHorarioAtendimento($domingo));
    }

    public function test_horario_atendimento_texto_agrupa_dias_consecutivos(): void
    {
        $this->assertSame('Seg–Sex 08:00–18:00', $this->tenant->horarioAtendimentoTexto());

        $semHorario = new Tenant(['horario_atendimento' => null]);
        $this->assertSame('', $semHorario->horarioAtendimentoTexto());
        $this->assertTrue($semHorario->emHorarioAtendimento());
    }
}
