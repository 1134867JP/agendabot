<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** O calendário precisa saber da falta para exibir "Faltou" e oferecer "Marcar falta". */
class AgendaCalendarioFaltaTest extends TestCase
{
    use RefreshDatabase;

    public function test_eventos_do_calendario_informam_a_falta(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Barbearia Calendário',
            'slug' => 'barbearia-calendario',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($user->id, ['papel' => 'admin']);
        $asafe = Profissional::create(['tenant_id' => $tenant->id, 'nome' => 'Asafe', 'ativo' => true]);
        $inicio = now()->subDay()->setTime(10, 0);
        $falta = Agendamento::create([
            'tenant_id' => $tenant->id, 'cliente_nome' => 'Lucas', 'cliente_telefone' => '5551944440001',
            'inicio' => $inicio, 'fim' => $inicio->copy()->addMinutes(30), 'status' => 'concluido', 'no_show' => true, 'origem' => 'manual', 'profissional_id' => $asafe->id,
        ]);
        $veio = Agendamento::create([
            'tenant_id' => $tenant->id, 'cliente_nome' => 'Rui', 'cliente_telefone' => '5551944440002',
            'inicio' => $inicio->copy()->addHour(), 'fim' => $inicio->copy()->addMinutes(90), 'status' => 'concluido', 'origem' => 'manual', 'profissional_id' => $asafe->id,
        ]);

        $eventos = collect($this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->getJson(route('tenant.agenda.disponibilidade', [
                'data_inicio' => $inicio->copy()->startOfDay()->toDateString(),
                'data_fim' => $inicio->toDateString(),
                'profissional_id' => $asafe->id,
            ]))
            ->assertOk()
            ->json())->where('tipo', 'agendamento')->keyBy('id');

        $this->assertTrue($eventos[$falta->id]['no_show']);
        $this->assertFalse($eventos[$veio->id]['no_show']);
    }
}
