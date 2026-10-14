<?php

namespace Tests\Unit;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\AgendamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Modelos de IA mandam data e hora em formatos variados ("2026-10-15T15:00:00",
 * "15/10", "15h"). Antes só "AAAA-MM-DD" + "HH:MM" era aceito e o resto virava
 * "Unexpected data found", com o bot respondendo "deu erro no sistema".
 */
class AgendamentoServiceDataHoraTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $profissional;

    private Cliente $cliente;

    private Carbon $dia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Datas',
            'slug' => 'barbearia-datas',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->profissional = Profissional::create(['tenant_id' => $this->tenant->id, 'nome' => 'Asafe', 'ativo' => true]);
        for ($d = 0; $d <= 6; $d++) {
            $this->profissional->horarios()->create(['dia_semana' => $d, 'hora_inicio' => '08:00', 'hora_fim' => '20:00', 'duracao_slot' => 30]);
        }
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'telefone' => '5551900000077', 'nome' => 'Rui']);
        $this->dia = Carbon::now($this->tenant->resolvedTimezone())->addDays(3)->startOfDay();
    }

    #[DataProvider('formatosAceitos')]
    public function test_cria_agendamento_com_formatos_variados_de_data_e_hora(string $formatoData, string $horario, string $esperado): void
    {
        $agendamento = $this->criar($this->dia->format($formatoData), $horario);

        $this->assertSame(
            $this->dia->format('Y-m-d').' '.$esperado,
            $agendamento->data_hora->setTimezone($this->tenant->resolvedTimezone())->format('Y-m-d H:i'),
        );
    }

    public static function formatosAceitos(): array
    {
        return [
            'padrão' => ['Y-m-d', '15:00', '15:00'],
            'data ISO com hora' => ['Y-m-d\TH:i:s', '15:00', '15:00'],
            'hora com segundos' => ['Y-m-d', '15:00:00', '15:00'],
            'hora "15h"' => ['Y-m-d', '15h', '15:00'],
            'hora "15h30"' => ['Y-m-d', '15h30', '15:30'],
            'hora de um dígito' => ['Y-m-d', '9:30', '09:30'],
            'data brasileira' => ['d/m/Y', '15:00', '15:00'],
            'data brasileira sem ano' => ['d/m', '15:00', '15:00'],
        ];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_recusa_data_ou_hora_invalida_com_mensagem_clara(string $data, string $horario): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/AAAA-MM-DD|HH:MM/');

        $this->criar($data, $horario);
    }

    public static function entradasInvalidas(): array
    {
        return [
            'data por extenso' => ['amanhã', '15:00'],
            'dia inexistente' => ['2030-02-30', '15:00'],
            'hora inexistente' => ['2030-01-10', '25:00'],
            'hora por extenso' => ['2030-01-10', 'de tarde'],
        ];
    }

    public function test_reagenda_com_hora_em_formato_variado(): void
    {
        $agendamento = $this->criar($this->dia->format('Y-m-d'), '10:00');

        app(AgendamentoService::class)->reagendar($agendamento, ['data' => $this->dia->format('d/m/Y'), 'hora' => '16h']);

        $this->assertSame('16:00', $agendamento->fresh()->data_hora->setTimezone($this->tenant->resolvedTimezone())->format('H:i'));
    }

    private function criar(string $data, string $horario): Agendamento
    {
        return app(AgendamentoService::class)->criarAgendamentoV2($this->tenant, [
            'profissional_id' => $this->profissional->id,
            'cliente_id' => $this->cliente->id,
            'cliente_nome' => 'Rui',
            'cliente_telefone' => $this->cliente->telefone,
            'data' => $data,
            'horario' => $horario,
            'origem' => 'bot',
        ]);
    }
}
