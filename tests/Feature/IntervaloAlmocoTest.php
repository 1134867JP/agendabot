<?php

namespace Tests\Feature;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgendamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntervaloAlmocoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Profissional $barbeiro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Almoço',
            'slug' => 'barbearia-almoco',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);
        $this->barbeiro = Profissional::create(['tenant_id' => $this->tenant->id, 'nome' => 'João', 'ativo' => true]);

        for ($dia = 0; $dia <= 6; $dia++) {
            $this->barbeiro->horarios()->create([
                'dia_semana' => $dia,
                'hora_inicio' => '09:00',
                'hora_fim' => '19:00',
                'intervalo_inicio' => '12:15',
                'intervalo_fim' => '13:15',
                'duracao_slot' => 30,
            ]);
        }
    }

    public function test_bot_nao_oferece_horario_durante_o_almoco_e_retoma_na_volta(): void
    {
        $amanha = Carbon::tomorrow($this->tenant->resolvedTimezone());

        $horas = collect($this->barbeiro->slotsDisponiveis($amanha))->pluck('hora');

        $this->assertContains('11:30', $horas);
        $this->assertNotContains('12:00', $horas, 'Slot 12:00–12:30 invade o almoço.');
        $this->assertNotContains('12:30', $horas);
        $this->assertNotContains('13:00', $horas);
        $this->assertContains('13:15', $horas, 'A grade recomeça na volta do almoço.');
        $this->assertContains('18:15', $horas);
    }

    public function test_rejeita_agendamento_que_invade_o_almoco(): void
    {
        $this->expectException(HorarioIndisponivelException::class);
        $this->expectExceptionMessage('intervalo');

        app(AgendamentoService::class)->criarAgendamentoV2($this->tenant, [
            'data' => now()->addDays(2)->format('Y-m-d'),
            'horario' => '12:00',
            'profissional_id' => $this->barbeiro->id,
            'cliente_nome' => 'Carlos',
            'cliente_telefone' => '5551999999999',
        ]);
    }

    public function test_salva_intervalo_pelo_painel_e_valida_limites(): void
    {
        $horarios = [[
            'dia_semana' => 2,
            'hora_inicio' => '09:00',
            'hora_fim' => '19:00',
            'intervalo_inicio' => '12:00',
            'intervalo_fim' => '13:00',
            'duracao_slot' => 30,
            'ativo' => true,
        ]];

        $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('tenant.profissionais.horarios.sync', $this->barbeiro), ['horarios' => $horarios])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('horarios_profissional', [
            'profissional_id' => $this->barbeiro->id,
            'dia_semana' => 2,
            'intervalo_inicio' => '12:00:00',
            'intervalo_fim' => '13:00:00',
        ]);

        $horarios[0]['intervalo_fim'] = '20:00';
        $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('tenant.profissionais.horarios.sync', $this->barbeiro), ['horarios' => $horarios])
            ->assertSessionHasErrors('horarios.0.intervalo_inicio');
    }
}
