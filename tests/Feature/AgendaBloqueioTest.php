<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use App\Models\HorarioProfissional;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaBloqueioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Profissional $profissional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Clínica Agenda',
            'slug' => 'clinica-agenda-bloqueios',
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);
        $this->profissional = Profissional::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Ana',
            'ativo' => true,
        ]);
    }

    private function autenticarComTenant()
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }

    public function test_cria_e_exibe_bloqueio_na_disponibilidade(): void
    {
        $inicio = now()->addDay()->setTime(9, 0);
        $fim = $inicio->copy()->addHour();

        $response = $this->autenticarComTenant()->post(route('tenant.agenda.bloqueios.store'), [
            'profissional_id' => $this->profissional->id,
            'inicio' => $inicio->toIso8601String(),
            'fim' => $fim->toIso8601String(),
            'motivo' => 'Reunião interna',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bloqueios_agenda', [
            'tenant_id' => $this->tenant->id,
            'profissional_id' => $this->profissional->id,
            'motivo' => 'Reunião interna',
        ]);

        $this->autenticarComTenant()
            ->getJson(route('tenant.agenda.disponibilidade', [
                'profissional_id' => $this->profissional->id,
                'data_inicio' => $inicio->copy()->startOfDay()->toIso8601String(),
                'data_fim' => $fim->copy()->endOfDay()->toIso8601String(),
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'tipo' => 'bloqueio',
                'title' => 'Reunião interna',
                'status' => 'bloqueado',
            ]);
    }

    public function test_bloqueio_remove_slot_oferecido_pelo_profissional(): void
    {
        $data = Carbon::tomorrow(config('app.timezone', 'America/Sao_Paulo'));
        $diaSemana = (int) $data->format('N');
        if ($diaSemana === 7) {
            $diaSemana = 0;
        }

        HorarioProfissional::create([
            'profissional_id' => $this->profissional->id,
            'dia_semana' => $diaSemana,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '12:00:00',
            'duracao_slot' => 60,
        ]);
        BloqueioAgenda::create([
            'tenant_id' => $this->tenant->id,
            'profissional_id' => $this->profissional->id,
            'inicio' => $data->copy()->setTime(9, 0),
            'fim' => $data->copy()->setTime(10, 0),
            'motivo' => 'Intervalo',
        ]);

        $slots = collect($this->profissional->slotsDisponiveis($data))->keyBy('hora');

        $this->assertTrue($slots['08:00']['disponivel']);
        $this->assertFalse($slots['09:00']['disponivel']);
        $this->assertTrue($slots['10:00']['disponivel']);
    }

    public function test_nao_bloqueia_periodo_com_reserva_existente(): void
    {
        $inicio = now()->addDay()->setTime(9, 0);
        Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente existente',
            'cliente_telefone' => '5554999999999',
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addHour(),
            'data_hora' => $inicio,
            'duracao_minutos' => 60,
            'status' => 'agendado',
            'origem' => 'manual',
        ]);

        $this->autenticarComTenant()
            ->post(route('tenant.agenda.bloqueios.store'), [
                'profissional_id' => $this->profissional->id,
                'inicio' => $inicio->copy()->addMinutes(30)->toIso8601String(),
                'fim' => $inicio->copy()->addMinutes(90)->toIso8601String(),
                'motivo' => 'Conflito',
            ])
            ->assertSessionHasErrors('inicio');

        $this->assertDatabaseMissing('bloqueios_agenda', [
            'tenant_id' => $this->tenant->id,
            'motivo' => 'Conflito',
        ]);
    }

    public function test_nao_remove_bloqueio_de_outro_tenant(): void
    {
        $outroTenant = Tenant::create([
            'nome' => 'Outro tenant',
            'slug' => 'outro-tenant-bloqueio',
            'tipo_servico' => 'clinica',
            'ativo' => true,
        ]);
        $outroProfissional = Profissional::create([
            'tenant_id' => $outroTenant->id,
            'nome' => 'Outro profissional',
            'ativo' => true,
        ]);
        $bloqueio = BloqueioAgenda::create([
            'tenant_id' => $outroTenant->id,
            'profissional_id' => $outroProfissional->id,
            'inicio' => now()->addDay(),
            'fim' => now()->addDay()->addHour(),
        ]);

        $this->autenticarComTenant()
            ->delete(route('tenant.agenda.bloqueios.destroy', $bloqueio))
            ->assertForbidden();

        $this->assertDatabaseHas('bloqueios_agenda', ['id' => $bloqueio->id]);
    }
}
