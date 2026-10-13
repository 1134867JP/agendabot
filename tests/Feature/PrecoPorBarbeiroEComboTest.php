<?php

namespace Tests\Feature;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgendamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrecoPorBarbeiroEComboTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Profissional $joao;

    private Profissional $pedro;

    private Servico $corte;

    private Servico $barba;

    private Servico $sobrancelha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Combo',
            'slug' => 'barbearia-combo',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);

        $this->corte = $this->tenant->servicos()->create(['nome' => 'Corte', 'valor_min' => 40, 'valor_max' => 40, 'duracao_minutos' => 30]);
        $this->barba = $this->tenant->servicos()->create(['nome' => 'Barba', 'valor_min' => 30, 'valor_max' => 30, 'duracao_minutos' => 30]);
        $this->sobrancelha = $this->tenant->servicos()->create(['nome' => 'Sobrancelha', 'valor_min' => 15, 'valor_max' => 15, 'duracao_minutos' => 15]);

        $this->joao = Profissional::create(['tenant_id' => $this->tenant->id, 'nome' => 'João', 'ativo' => true]);
        $this->pedro = Profissional::create(['tenant_id' => $this->tenant->id, 'nome' => 'Pedro', 'ativo' => true]);

        // João é o barbeiro sênior: corte mais caro e mais demorado; não faz sobrancelha.
        $this->joao->servicos()->sync([
            $this->corte->id => ['valor' => 55, 'duracao_minutos' => 45],
            $this->barba->id => ['valor' => null, 'duracao_minutos' => null],
        ]);
        $this->pedro->servicos()->sync([$this->corte->id, $this->barba->id, $this->sobrancelha->id]);

        foreach ([$this->joao, $this->pedro] as $profissional) {
            for ($dia = 0; $dia <= 6; $dia++) {
                $profissional->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fim' => '19:00', 'duracao_slot' => 30]);
            }
        }
    }

    public function test_bot_usa_preco_e_duracao_do_barbeiro(): void
    {
        $agendamento = $this->criarPeloBot($this->joao, [$this->corte->id]);

        $this->assertSame(45, $agendamento->duracao_minutos);
        $this->assertEquals(55, $agendamento->valor_total);
        $this->assertSame('Corte', $agendamento->descricaoServicos());
    }

    public function test_combo_soma_duracao_e_valor_e_guarda_os_servicos(): void
    {
        $agendamento = $this->criarPeloBot($this->joao, [$this->corte->id, $this->barba->id]);

        $this->assertSame(75, $agendamento->duracao_minutos);
        $this->assertEquals(85, $agendamento->valor_total);
        $this->assertSame('Corte + Barba', $agendamento->descricaoServicos());
        $this->assertSame($this->corte->id, $agendamento->servico_id);
        $this->assertSame(75, (int) $agendamento->inicio->diffInMinutes($agendamento->fim));
    }

    public function test_recusa_servico_que_o_barbeiro_nao_faz_e_indica_quem_faz(): void
    {
        $this->expectException(HorarioIndisponivelException::class);
        $this->expectExceptionMessage('Quem faz: Pedro');

        $this->criarPeloBot($this->joao, [$this->corte->id, $this->sobrancelha->id]);
    }

    public function test_busca_de_horarios_so_oferece_onde_o_combo_cabe(): void
    {
        $amanha = Carbon::tomorrow($this->tenant->resolvedTimezone());
        $this->criarPeloBot($this->pedro, [$this->corte->id], $amanha->format('Y-m-d'), '10:00');

        $slots = app(AgendamentoService::class)
            ->buscarHorariosDisponiveis($this->tenant, 2, [$this->corte->id, $this->barba->id, $this->sobrancelha->id]);

        // Só Pedro faz os três serviços; o combo dura 75 min.
        $this->assertArrayNotHasKey($this->joao->id, $slots);
        $horasPedro = $slots[$this->pedro->id][$amanha->format('Y-m-d')];
        $this->assertNotContains('09:00', $horasPedro, '09:00–10:15 invade o corte das 10:00.');
        $this->assertContains('10:30', $horasPedro);
        $this->assertContains('17:30', $horasPedro);
        $this->assertNotContains('18:00', $horasPedro, '18:00–19:15 passa do fim do expediente.');
    }

    public function test_reagendar_mantem_o_combo_e_recalcula_para_o_novo_barbeiro(): void
    {
        $agendamento = $this->criarPeloBot($this->joao, [$this->corte->id, $this->barba->id]);

        $novo = app(AgendamentoService::class)->reagendar($agendamento, [
            'data' => now()->addDays(3)->format('Y-m-d'),
            'hora' => '14:00',
            'profissional_id' => $this->pedro->id,
        ]);

        $this->assertSame(60, $novo->duracao_minutos);
        $this->assertEquals(70, $novo->valor_total);
        $this->assertSame('Corte + Barba', $novo->descricaoServicos());
    }

    public function test_painel_cria_reserva_manual_com_combo(): void
    {
        $inicio = now()->addDays(2)->setTime(15, 0);

        $this->painel()->post(route('tenant.agendamentos.store'), [
            'profissional_id' => $this->joao->id,
            'servico_ids' => [$this->corte->id, $this->barba->id],
            'cliente_nome' => 'Carlos',
            'cliente_telefone' => '51999999999',
            'inicio' => $inicio->format('Y-m-d\TH:i:s'),
            'fim' => $inicio->copy()->addMinutes(30)->format('Y-m-d\TH:i:s'),
        ])->assertSessionHasNoErrors();

        $agendamento = Agendamento::where('cliente_nome', 'Carlos')->firstOrFail();
        $this->assertEquals(85, $agendamento->valor_total);
        $this->assertSame(75, $agendamento->duracao_minutos);
        $this->assertSame('Corte + Barba', $agendamento->descricaoServicos());
    }

    public function test_salva_servicos_e_precos_do_barbeiro_pelo_painel(): void
    {
        $this->painel()->post(route('tenant.profissionais.servicos.sync', $this->pedro), [
            'servicos' => [
                ['servico_id' => $this->corte->id, 'valor' => 45, 'duracao_minutos' => null],
                ['servico_id' => $this->barba->id, 'valor' => null, 'duracao_minutos' => 40],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('profissional_servico', ['profissional_id' => $this->pedro->id, 'servico_id' => $this->corte->id, 'valor' => 45]);
        $this->assertDatabaseHas('profissional_servico', ['profissional_id' => $this->pedro->id, 'servico_id' => $this->barba->id, 'duracao_minutos' => 40]);
        $this->assertDatabaseMissing('profissional_servico', ['profissional_id' => $this->pedro->id, 'servico_id' => $this->sobrancelha->id]);
    }

    public function test_editar_ou_desativar_barbeiro_nao_apaga_os_servicos_dele(): void
    {
        $this->painel()->put(route('tenant.profissionais.update', $this->joao), [
            'nome' => 'João Silva',
            'especialidades' => [],
            'ativo' => false,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->joao->servicos()->count());
    }

    private function criarPeloBot(Profissional $profissional, array $servicoIds, ?string $data = null, string $horario = '15:00'): Agendamento
    {
        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone' => '5551988887777'],
            ['nome' => 'Cliente Bot'],
        );

        return app(AgendamentoService::class)->criarAgendamentoV2($this->tenant, [
            'cliente_id' => $cliente->id,
            'data' => $data ?? now()->addDays(2)->format('Y-m-d'),
            'horario' => $horario,
            'profissional_id' => $profissional->id,
            'servico_ids' => $servicoIds,
            'cliente_nome' => 'Cliente Bot',
            'cliente_telefone' => '5551988887777',
        ])->fresh();
    }

    private function painel()
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }
}
