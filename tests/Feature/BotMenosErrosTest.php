<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Services\ConversaSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Contexto que evita erros comuns dos modelos menores (nome, calendário, busca de horários). */
class BotMenosErrosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $asafe;

    private Servico $corte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Erros',
            'slug' => 'barbearia-erros',
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
        for ($dia = 0; $dia <= 6; $dia++) {
            $this->asafe->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fim' => '18:00', 'duracao_slot' => 30]);
        }
    }

    public function test_prompt_traz_o_nome_do_cliente_cadastrado(): void
    {
        $sistema = $this->sistemaEnviado('5551922000001', 'João Pedro');

        $this->assertStringContainsString('NOME DO CLIENTE (cadastro): João Pedro', $sistema);
    }

    public function test_prompt_nao_traz_nome_generico_ou_telefone(): void
    {
        $sistema = $this->sistemaEnviado('5551922000002', null);

        $this->assertStringNotContainsString('NOME DO CLIENTE', $sistema);
    }

    public function test_prompt_traz_os_proximos_dias_com_dia_da_semana(): void
    {
        $sistema = $this->sistemaEnviado('5551922000003', 'Bruno');

        $amanha = Carbon::tomorrow($this->tenant->resolvedTimezone())->locale('pt_BR');
        $depois = $amanha->copy()->addDay();
        $this->assertStringContainsString("amanhã = {$amanha->translatedFormat('l')} {$amanha->format('d/m')} ({$amanha->format('Y-m-d')})", $sistema);
        $this->assertStringContainsString("{$depois->translatedFormat('l')} {$depois->format('d/m')} ({$depois->format('Y-m-d')})", $sistema);
    }

    public function test_buscar_slots_com_um_dia_ainda_inclui_amanha(): void
    {
        // O modelo pedia dias=1 achando que era "amanhã"; a busca só cobria hoje
        // e o bot respondia "não há horários amanhã".
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'buscar_slots', 'input' => ['dias' => 1, 'servico_ids' => [$this->corte->id]]]],
                    'stop_reason' => 'tool_use',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ])
                ->push([
                    'content' => [['type' => 'text', 'text' => 'Tenho horários amanhã.']],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        $this->enviar('5551922000004', 'tem horário amanhã?', 'Bruno');

        $segunda = Http::recorded(fn ($r) => str_contains($r->url(), 'anthropic.com'))->values()[1][0]->data();
        $toolResult = collect(end($segunda['messages'])['content'])->firstWhere('type', 'tool_result');
        $amanha = Carbon::tomorrow($this->tenant->resolvedTimezone())->format('Y-m-d');
        $this->assertStringContainsString($amanha, json_decode($toolResult['content'], true)['slots']);
    }

    public function test_prompt_orienta_confirmar_direto_horario_livre(): void
    {
        $sistema = $this->sistemaEnviado('5551922000005', 'Bruno');

        $this->assertStringContainsString('Se o cliente já disse dia e horário e esse horário está livre', $sistema);
    }

    private function sistemaEnviado(string $telefone, ?string $pushName): string
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Olá!']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);
        $this->enviar($telefone, 'oi', $pushName);
        $dados = Http::recorded(fn ($r) => str_contains($r->url(), 'anthropic.com'))->last()[0]->data();

        return collect($dados['system'])->pluck('text')->join("\n");
    }

    private function enviar(string $telefone, string $texto, ?string $pushName): void
    {
        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida($this->tenant, $telefone, $texto, 'MSG_'.$telefone.md5($texto), $pushName);
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $mensagem->id);
    }
}
