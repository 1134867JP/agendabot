<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendamentoManualValidacaoTest extends TestCase
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
            'nome' => 'Barbearia Manual',
            'slug' => 'barbearia-manual',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);

        $this->profissional = Profissional::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Profissional Manual',
            'ativo' => true,
        ]);

        // Expediente Seg-Sex, 08:00-18:00
        foreach ([1, 2, 3, 4, 5] as $dia) {
            $this->profissional->horarios()->create([
                'dia_semana' => $dia,
                'hora_inicio' => '08:00',
                'hora_fim' => '18:00',
                'duracao_slot' => 30,
            ]);
        }
    }

    private function autenticarComTenant()
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }

    private function proximaSegundaAs(int $hora, int $minuto = 0)
    {
        return now()->next('Monday')->setTime($hora, $minuto, 0);
    }

    public function test_rejeita_criacao_manual_fora_do_expediente_do_profissional(): void
    {
        $inicio = $this->proximaSegundaAs(20); // fora do expediente (08h-18h)
        $fim = $inicio->copy()->addMinutes(30);

        $response = $this->autenticarComTenant()->post(route('tenant.agendamentos.store'), [
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente Teste',
            'cliente_telefone' => '51999999999',
            'inicio' => $inicio->toDateTimeString(),
            'fim' => $fim->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors([
            'inicio' => 'Profissional Manual atende neste dia das 08:00 às 18:00. Escolha um horário dentro desse período.',
        ]);
        $this->assertDatabaseMissing('agendamentos', ['cliente_nome' => 'Cliente Teste']);
    }

    public function test_visao_geral_da_agenda_retorna_profissional_da_reserva(): void
    {
        $inicio = $this->proximaSegundaAs(10);

        Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente Visão Geral',
            'cliente_telefone' => '54999999999',
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'data_hora' => $inicio,
            'duracao_minutos' => 30,
            'status' => 'agendado',
            'origem' => 'manual',
        ]);

        $response = $this->autenticarComTenant()->get(route('tenant.agenda.disponibilidade', [
            'todos_profissionais' => 1,
            'data_inicio' => $inicio->copy()->startOfDay()->toDateString(),
            'data_fim' => $inicio->copy()->endOfDay()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'title' => 'Cliente Visão Geral',
                'profissional_id' => $this->profissional->id,
                'profissional_nome' => 'Profissional Manual',
            ]);
    }

    public function test_rejeita_telefone_invalido_na_criacao_manual(): void
    {
        $inicio = $this->proximaSegundaAs(10);
        $fim = $inicio->copy()->addMinutes(30);

        $response = $this->autenticarComTenant()->post(route('tenant.agendamentos.store'), [
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente Telefone Inválido',
            'cliente_telefone' => '54984',
            'inicio' => $inicio->toDateTimeString(),
            'fim' => $fim->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('cliente_telefone');
        $this->assertDatabaseMissing('agendamentos', ['cliente_nome' => 'Cliente Telefone Inválido']);
    }

    public function test_aceita_telefone_formatado_e_salva_apenas_digitos(): void
    {
        $inicio = $this->proximaSegundaAs(10);
        $fim = $inicio->copy()->addMinutes(30);

        $response = $this->autenticarComTenant()->post(route('tenant.agendamentos.store'), [
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente Telefone Válido',
            'cliente_telefone' => '(54) 99999-9999',
            'inicio' => $inicio->toDateTimeString(),
            'fim' => $fim->toDateTimeString(),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('agendamentos', [
            'cliente_nome' => 'Cliente Telefone Válido',
            'cliente_telefone' => '54999999999',
        ]);
    }

    public function test_rejeita_criacao_manual_dentro_da_antecedencia_minima_configurada(): void
    {
        $this->tenant->update([
            'configuracoes' => array_merge($this->tenant->configuracoes ?? [], [
                'regras_agendamento' => ['antecedencia_minima_minutos' => 120],
            ]),
        ]);

        $inicio = now()->addMinutes(30);
        $fim = $inicio->copy()->addMinutes(30);

        $response = $this->autenticarComTenant()->post(route('tenant.agendamentos.store'), [
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente Teste',
            'cliente_telefone' => '51999999999',
            'inicio' => $inicio->toDateTimeString(),
            'fim' => $fim->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('inicio');
        $this->assertDatabaseMissing('agendamentos', ['cliente_nome' => 'Cliente Teste']);
    }

    public function test_rejeita_criacao_manual_colada_a_outro_respeitando_buffer(): void
    {
        $this->tenant->update([
            'configuracoes' => array_merge($this->tenant->configuracoes ?? [], [
                'regras_agendamento' => ['buffer_entre_agendamentos_minutos' => 15],
            ]),
        ]);

        $inicioExistente = $this->proximaSegundaAs(10);
        $fimExistente = $inicioExistente->copy()->addMinutes(30);

        Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente Existente',
            'cliente_telefone' => '51900000000',
            'inicio' => $inicioExistente,
            'fim' => $fimExistente,
            'data_hora' => $inicioExistente,
            'duracao_minutos' => 30,
            'status' => 'agendado',
            'origem' => 'manual',
        ]);

        // Começa exatamente quando o existente termina — sem buffer seria permitido
        $novoInicio = $fimExistente->copy();
        $novoFim = $novoInicio->copy()->addMinutes(30);

        $response = $this->autenticarComTenant()->post(route('tenant.agendamentos.store'), [
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente Novo',
            'cliente_telefone' => '51999999999',
            'inicio' => $novoInicio->toDateTimeString(),
            'fim' => $novoFim->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('inicio');
        $this->assertDatabaseMissing('agendamentos', ['cliente_nome' => 'Cliente Novo']);
    }

    public function test_permite_criacao_manual_dentro_do_expediente_e_regras(): void
    {
        $inicio = $this->proximaSegundaAs(10);
        $fim = $inicio->copy()->addMinutes(30);

        $response = $this->autenticarComTenant()->post(route('tenant.agendamentos.store'), [
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente Válido',
            'cliente_telefone' => '51999999999',
            'inicio' => $inicio->toDateTimeString(),
            'fim' => $fim->toDateTimeString(),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('agendamentos', ['cliente_nome' => 'Cliente Válido']);
    }

    public function test_update_rejeita_mover_para_horario_conflitante(): void
    {
        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone' => '51988888888',
            'nome' => 'Cliente A',
        ]);

        $inicioOcupado = $this->proximaSegundaAs(14);
        $fimOcupado = $inicioOcupado->copy()->addMinutes(30);

        Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente Ocupante',
            'cliente_telefone' => '51977777777',
            'inicio' => $inicioOcupado,
            'fim' => $fimOcupado,
            'data_hora' => $inicioOcupado,
            'duracao_minutos' => 30,
            'status' => 'agendado',
            'origem' => 'manual',
        ]);

        $inicioMovel = $this->proximaSegundaAs(9);
        $fimMovel = $inicioMovel->copy()->addMinutes(30);

        $agendamentoMovel = Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'profissional_id' => $this->profissional->id,
            'cliente_nome' => 'Cliente A',
            'cliente_telefone' => '51988888888',
            'inicio' => $inicioMovel,
            'fim' => $fimMovel,
            'data_hora' => $inicioMovel,
            'duracao_minutos' => 30,
            'status' => 'agendado',
            'origem' => 'manual',
        ]);

        $response = $this->autenticarComTenant()->put(route('tenant.agendamentos.update', $agendamentoMovel), [
            'cliente_nome' => 'Cliente A',
            'cliente_telefone' => '51988888888',
            'inicio' => $inicioOcupado->toDateTimeString(),
            'fim' => $fimOcupado->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors('inicio');
        $this->assertSame(
            $inicioMovel->toDateTimeString(),
            $agendamentoMovel->fresh()->inicio->toDateTimeString()
        );
    }
}
