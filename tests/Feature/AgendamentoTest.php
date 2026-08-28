<?php

namespace Tests\Feature;

use App\Exceptions\HorarioIndisponivelException;
use App\Models\Agendamento;
use App\Models\Recurso;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgendamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgendamentoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Recurso $recurso;

    private AgendamentoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Teste',
            'slug' => 'barbearia-teste',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
        $this->recurso = Recurso::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Barbeiro Teste',
            'ativo' => true,
        ]);

        $this->service = app(AgendamentoService::class);
    }

    public function test_cria_agendamento_com_sucesso(): void
    {
        $dados = [
            'tenant_id' => $this->tenant->id,
            'recurso_id' => $this->recurso->id,
            'cliente_nome' => 'João Silva',
            'cliente_telefone' => '51999999999',
            'inicio' => now()->addDay()->setHour(9)->setMinute(0)->setSecond(0),
            'fim' => now()->addDay()->setHour(9)->setMinute(30)->setSecond(0),
            'status' => 'confirmado',
            'origem' => 'manual',
        ];

        $agendamento = $this->service->criar($this->tenant, $dados);

        $this->assertInstanceOf(Agendamento::class, $agendamento);
        $this->assertDatabaseHas('agendamentos', [
            'tenant_id' => $this->tenant->id,
            'recurso_id' => $this->recurso->id,
            'cliente_nome' => 'João Silva',
            'status' => 'confirmado',
        ]);
    }

    public function test_rejeita_double_booking(): void
    {
        $inicio = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
        $fim = now()->addDay()->setHour(10)->setMinute(30)->setSecond(0);

        // Criar primeiro agendamento diretamente (sem passar pelo lock)
        Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'recurso_id' => $this->recurso->id,
            'cliente_nome' => 'Maria',
            'cliente_telefone' => '51988888888',
            'inicio' => $inicio,
            'fim' => $fim,
            'status' => 'confirmado',
            'origem' => 'whatsapp',
        ]);

        $this->expectException(HorarioIndisponivelException::class);

        // Tentar criar conflitante no mesmo horário e mesmo recurso
        $this->service->criar($this->tenant, [
            'tenant_id' => $this->tenant->id,
            'recurso_id' => $this->recurso->id,
            'cliente_nome' => 'Pedro',
            'cliente_telefone' => '51977777777',
            'inicio' => $inicio,
            'fim' => $fim,
            'status' => 'confirmado',
            'origem' => 'manual',
        ]);
    }

    public function test_permite_agendamento_apos_horario_cancelado(): void
    {
        $inicio = now()->addDay()->setHour(14)->setMinute(0)->setSecond(0);
        $fim = now()->addDay()->setHour(14)->setMinute(30)->setSecond(0);

        Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'recurso_id' => $this->recurso->id,
            'cliente_nome' => 'Ana',
            'cliente_telefone' => '51966666666',
            'inicio' => $inicio,
            'fim' => $fim,
            'status' => 'cancelado',
            'origem' => 'whatsapp',
        ]);

        $agendamento = $this->service->criar($this->tenant, [
            'tenant_id' => $this->tenant->id,
            'recurso_id' => $this->recurso->id,
            'cliente_nome' => 'Carlos',
            'cliente_telefone' => '51955555555',
            'inicio' => $inicio,
            'fim' => $fim,
            'status' => 'confirmado',
            'origem' => 'manual',
        ]);

        $this->assertEquals('confirmado', $agendamento->status);
    }

    public function test_cancela_agendamento(): void
    {
        $agendamento = Agendamento::create([
            'tenant_id' => $this->tenant->id,
            'cliente_nome' => 'Lucas',
            'cliente_telefone' => '51944444444',
            'inicio' => now()->addDay()->setHour(11)->setMinute(0)->setSecond(0),
            'fim' => now()->addDay()->setHour(11)->setMinute(30)->setSecond(0),
            'status' => 'confirmado',
            'origem' => 'manual',
        ]);

        $this->service->cancelar($agendamento);

        $this->assertDatabaseHas('agendamentos', [
            'id' => $agendamento->id,
            'status' => 'cancelado',
        ]);
    }
}
