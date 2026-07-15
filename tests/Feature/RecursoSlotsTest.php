<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Recurso;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecursoSlotsTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $regras = []): Tenant
    {
        return Tenant::create([
            'nome' => 'Arena',
            'slug' => 'arena-'.uniqid(),
            'tipo_servico' => 'quadra',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'configuracoes' => $regras ? ['regras_agendamento' => $regras] : null,
        ]);
    }

    private function recursoComHorario(Tenant $tenant, int $diaSemana): Recurso
    {
        $recurso = Recurso::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Quadra 1',
            'duracao_padrao_minutos' => 60,
            'ativo' => true,
        ]);

        $recurso->horariosFuncionamento()->create([
            'dia_semana' => $diaSemana,
            'abertura' => '08:00',
            'fechamento' => '12:00',
        ]);

        return $recurso;
    }

    public function test_slot_adjacente_fica_disponivel_sem_buffer(): void
    {
        $data = Carbon::parse('next monday');
        $tenant = $this->tenant(); // buffer default 0
        $recurso = $this->recursoComHorario($tenant, $data->dayOfWeek);

        // Agendamento 08:00–09:00. O slot das 09:00 é adjacente e deve ficar livre.
        Agendamento::create([
            'tenant_id' => $tenant->id,
            'recurso_id' => $recurso->id,
            'cliente_nome' => 'A',
            'cliente_telefone' => '51999999999',
            'inicio' => $data->copy()->setTime(8, 0),
            'fim' => $data->copy()->setTime(9, 0),
            'status' => 'confirmado',
            'origem' => 'manual',
        ]);

        $slots = collect($recurso->slotsDisponiveis($data));

        $this->assertFalse($slots->firstWhere('hora', '08:00')['disponivel']);
        $this->assertTrue($slots->firstWhere('hora', '09:00')['disponivel']);
    }

    public function test_buffer_bloqueia_slot_adjacente(): void
    {
        $data = Carbon::parse('next monday');
        $tenant = $this->tenant(['buffer_entre_agendamentos_minutos' => 30]);
        $recurso = $this->recursoComHorario($tenant, $data->dayOfWeek);

        // Mesmo agendamento 08:00–09:00, mas agora com buffer de 30min: o slot das
        // 09:00 encosta na janela expandida (até 09:30) e deve ficar indisponível.
        Agendamento::create([
            'tenant_id' => $tenant->id,
            'recurso_id' => $recurso->id,
            'cliente_nome' => 'A',
            'cliente_telefone' => '51999999999',
            'inicio' => $data->copy()->setTime(8, 0),
            'fim' => $data->copy()->setTime(9, 0),
            'status' => 'confirmado',
            'origem' => 'manual',
        ]);

        $slots = collect($recurso->slotsDisponiveis($data));

        $this->assertFalse($slots->firstWhere('hora', '09:00')['disponivel']);
        // 10:00 já está fora do alcance do buffer e permanece livre.
        $this->assertTrue($slots->firstWhere('hora', '10:00')['disponivel']);
    }
}
