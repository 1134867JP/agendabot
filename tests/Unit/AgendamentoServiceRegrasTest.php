<?php

namespace Tests\Unit;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\Cliente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgendamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendamentoServiceRegrasTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $profissional;

    private Cliente $cliente;

    private AgendamentoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Regras Service',
            'slug' => 'barbearia-regras-service',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);

        $this->profissional = Profissional::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Profissional Teste',
            'ativo' => true,
        ]);

        // Expediente todos os dias, 08:00–20:00, para não interferir nas regras testadas
        for ($dia = 0; $dia <= 6; $dia++) {
            $this->profissional->horarios()->create([
                'dia_semana' => $dia,
                'hora_inicio' => '08:00',
                'hora_fim' => '20:00',
                'duracao_slot' => 30,
            ]);
        }

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone' => '5551900000010',
            'nome' => 'Cliente Regras',
        ]);

        $this->service = app(AgendamentoService::class);
    }

    private function configurarRegras(array $overrides): void
    {
        $this->tenant->update([
            'configuracoes' => array_merge($this->tenant->configuracoes ?? [], [
                'regras_agendamento' => array_merge([
                    'antecedencia_minima_minutos' => 30,
                    'antecedencia_maxima_dias' => 60,
                    'buffer_entre_agendamentos_minutos' => 0,
                    'permite_cliente_remarcar' => true,
                    'permite_cliente_cancelar' => true,
                    'politica_cancelamento' => null,
                ], $overrides),
            ]),
        ]);
        $this->tenant->refresh();
    }

    public function test_rejeita_agendamento_dentro_da_antecedencia_minima_configurada(): void
    {
        $this->configurarRegras(['antecedencia_minima_minutos' => 120]);

        $this->expectException(HorarioIndisponivelException::class);

        $daqui30min = now()->addMinutes(30);

        $this->service->criarAgendamentoV2($this->tenant, [
            'data' => $daqui30min->format('Y-m-d'),
            'horario' => $daqui30min->format('H:i'),
            'profissional_id' => $this->profissional->id,
            'cliente_id' => $this->cliente->id,
            'cliente_nome' => $this->cliente->nome,
            'cliente_telefone' => $this->cliente->telefone,
        ]);
    }

    public function test_permite_agendamento_fora_da_antecedencia_minima_configurada(): void
    {
        $this->configurarRegras(['antecedencia_minima_minutos' => 60]);

        // Amanhã às 10h está sempre a mais de 60min de distância de "agora", independente
        // do horário em que o teste rodar, e sempre dentro do expediente configurado (08h–20h).
        $amanha10h = now()->addDay()->setTime(10, 0);

        $agendamento = $this->service->criarAgendamentoV2($this->tenant, [
            'data' => $amanha10h->format('Y-m-d'),
            'horario' => $amanha10h->format('H:i'),
            'profissional_id' => $this->profissional->id,
            'cliente_id' => $this->cliente->id,
            'cliente_nome' => $this->cliente->nome,
            'cliente_telefone' => $this->cliente->telefone,
        ]);

        $this->assertSame('agendado', $agendamento->status);
    }

    public function test_rejeita_agendamento_alem_da_antecedencia_maxima_configurada(): void
    {
        $this->configurarRegras(['antecedencia_maxima_dias' => 5]);

        $this->expectException(HorarioIndisponivelException::class);

        $daqui10dias = now()->addDays(10)->setTime(10, 0);

        $this->service->criarAgendamentoV2($this->tenant, [
            'data' => $daqui10dias->format('Y-m-d'),
            'horario' => $daqui10dias->format('H:i'),
            'profissional_id' => $this->profissional->id,
            'cliente_id' => $this->cliente->id,
            'cliente_nome' => $this->cliente->nome,
            'cliente_telefone' => $this->cliente->telefone,
        ]);
    }

    public function test_buffer_entre_agendamentos_bloqueia_horario_muito_proximo_do_anterior(): void
    {
        $this->configurarRegras(['buffer_entre_agendamentos_minutos' => 15, 'antecedencia_minima_minutos' => 0]);

        $amanha = now()->addDay()->setTime(10, 0);

        $this->service->criarAgendamentoV2($this->tenant, [
            'data' => $amanha->format('Y-m-d'),
            'horario' => $amanha->format('H:i'),
            'duracao_minutos' => 30,
            'profissional_id' => $this->profissional->id,
            'cliente_id' => $this->cliente->id,
            'cliente_nome' => $this->cliente->nome,
            'cliente_telefone' => $this->cliente->telefone,
        ]);

        // Segundo agendamento começa exatamente quando o primeiro termina (10:30) —
        // sem buffer seria permitido, mas com 15min de folga configurados deve ser rejeitado.
        $this->expectException(HorarioIndisponivelException::class);

        $this->service->criarAgendamentoV2($this->tenant, [
            'data' => $amanha->format('Y-m-d'),
            'horario' => $amanha->copy()->addMinutes(30)->format('H:i'),
            'duracao_minutos' => 30,
            'profissional_id' => $this->profissional->id,
            'cliente_id' => $this->cliente->id,
            'cliente_nome' => $this->cliente->nome,
            'cliente_telefone' => $this->cliente->telefone,
        ]);
    }
}
