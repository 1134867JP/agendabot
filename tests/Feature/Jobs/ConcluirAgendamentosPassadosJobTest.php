<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ConcluirAgendamentosPassadosJob;
use App\Models\Agendamento;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConcluirAgendamentosPassadosJobTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Passados',
            'slug' => 'barbearia-passados',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function test_conclui_agendamentos_que_terminaram_ha_mais_de_tres_horas(): void
    {
        $agendado = $this->agendamento('agendado', fimHaHoras: 4);
        $confirmado = $this->agendamento('confirmado', fimHaHoras: 30);

        ConcluirAgendamentosPassadosJob::dispatchSync();

        $this->assertSame('concluido', $agendado->fresh()->status);
        $this->assertSame('concluido', $confirmado->fresh()->status);
        $this->assertFalse($agendado->fresh()->no_show);
    }

    public function test_nao_mexe_em_recentes_futuros_cancelados_nem_faltas(): void
    {
        $recente = $this->agendamento('agendado', fimHaHoras: 1);
        $futuro = $this->agendamento('confirmado', fimHaHoras: -24);
        $cancelado = $this->agendamento('cancelado', fimHaHoras: 48);
        $falta = $this->agendamento('concluido', fimHaHoras: 48, noShow: true);

        ConcluirAgendamentosPassadosJob::dispatchSync();

        $this->assertSame('agendado', $recente->fresh()->status);
        $this->assertSame('confirmado', $futuro->fresh()->status);
        $this->assertSame('cancelado', $cancelado->fresh()->status);
        $this->assertTrue($falta->fresh()->no_show);
    }

    private function agendamento(string $status, int $fimHaHoras, bool $noShow = false): Agendamento
    {
        $fim = now()->subHours($fimHaHoras);

        return Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'cliente_nome' => 'Cliente',
            'cliente_telefone' => '5551900000'.random_int(100, 999),
            'inicio' => $fim->copy()->subMinutes(30),
            'fim' => $fim,
            'data_hora' => $fim->copy()->subMinutes(30),
            'duracao_minutos' => 30,
            'status' => $status,
            'no_show' => $noShow,
            'origem' => 'manual',
        ]);
    }
}
