<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Services\ConversaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O prompt e as ferramentas enviados à IA trazem só o que serve para a conversa:
 * contexto irrelevante custa tokens e faz modelos menores escolherem a ação errada.
 */
class BotPromptEnxutoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $asafe;

    private Servico $corte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Enxuta',
            'slug' => 'barbearia-enxuta',
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
        $this->asafe->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fim' => '18:00', 'duracao_slot' => 30]);
    }

    public function test_cliente_sem_agendamento_recebe_so_o_necessario_para_agendar(): void
    {
        [$sistema, $ferramentas] = $this->requisicaoParaIa('5551944000001');

        $this->assertSame(['buscar_slots', 'criar_agendamento', 'transferir_para_humano'], $ferramentas);
        $this->assertStringNotContainsString('FLUXO OBRIGATÓRIO PARA REAGENDAMENTO', $sistema);
        $this->assertStringContainsString('não tem agendamento futuro', $sistema);
    }

    public function test_cliente_com_agendamento_recebe_ferramentas_e_fluxo_de_gestao(): void
    {
        $this->agendamentoFuturo('5551944000002');

        [$sistema, $ferramentas] = $this->requisicaoParaIa('5551944000002');

        $this->assertSame(
            ['buscar_slots', 'confirmar_agendamento', 'cancelar_agendamento', 'listar_agendamentos_cliente', 'reagendar_agendamento', 'transferir_para_humano'],
            $ferramentas,
        );
        $this->assertStringContainsString('FLUXO OBRIGATÓRIO PARA REAGENDAMENTO', $sistema);
        $this->assertStringNotContainsString('não tem agendamento futuro', $sistema);
    }

    public function test_profissional_sem_horarios_nao_aparece_no_prompt(): void
    {
        // Sem horário cadastrado ele nunca tem slot livre; listá-lo só levava o bot a oferecê-lo.
        Profissional::create(['tenant_id' => $this->tenant->id, 'nome' => 'Zeca', 'ativo' => true]);

        [$sistema] = $this->requisicaoParaIa('5551944000003');

        $this->assertStringContainsString("ID {$this->asafe->id}: Asafe", $sistema);
        $this->assertStringNotContainsString('Zeca', $sistema);
    }

    public function test_bloco_de_varios_servicos_so_aparece_com_mais_de_um_servico(): void
    {
        [$sistema] = $this->requisicaoParaIa('5551944000004');
        $this->assertStringNotContainsString('VÁRIOS SERVIÇOS NO MESMO HORÁRIO', $sistema);

        Servico::create(['tenant_id' => $this->tenant->id, 'nome' => 'Barba', 'duracao_minutos' => 20, 'ativo' => true]);
        [$sistema] = $this->requisicaoParaIa('5551944000005');
        $this->assertStringContainsString('VÁRIOS SERVIÇOS NO MESMO HORÁRIO', $sistema);
    }

    public function test_endereco_vazio_nao_gera_linha_quebrada(): void
    {
        [$sistema] = $this->requisicaoParaIa('5551944000006');
        $this->assertStringNotContainsString('Local: ,', $sistema);

        $this->tenant->update(['endereco' => 'Rua A, 10', 'cidade' => 'Vacaria']);
        [$sistema] = $this->requisicaoParaIa('5551944000007');
        $this->assertStringContainsString('Local: Rua A, 10, Vacaria', $sistema);
    }

    private function agendamentoFuturo(string $telefone): void
    {
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'telefone' => $telefone, 'nome' => 'Bruno']);
        $inicio = now()->addDays(2)->setTime(10, 0);
        Agendamento::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $cliente->id, 'cliente_nome' => 'Bruno',
            'cliente_telefone' => $telefone, 'profissional_id' => $this->asafe->id, 'servico_id' => $this->corte->id,
            'data_hora' => $inicio, 'inicio' => $inicio, 'fim' => $inicio->copy()->addMinutes(30),
            'duracao_minutos' => 30, 'status' => 'agendado', 'origem' => 'bot',
        ]);
    }

    /** @return array{0: string, 1: list<string>} texto de sistema e nomes das ferramentas enviados à IA */
    private function requisicaoParaIa(string $telefone): array
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Olá!']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);
        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida($this->tenant, $telefone, 'oi', 'MSG_'.$telefone, 'Bruno');
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant->fresh(), $telefone, $mensagem->id);

        $dados = Http::recorded(fn ($request) => str_contains($request->url(), 'anthropic.com'))->last()[0]->data();

        return [
            collect($dados['system'])->pluck('text')->join("\n"),
            collect($dados['tools'])->pluck('name')->all(),
        ];
    }
}
