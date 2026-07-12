<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnviarLembretesJob;
use App\Models\Agendamento;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EvolutionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnviarLembretesJobTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Lembretes',
            'slug' => 'barbearia-lembretes',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'whatsapp_conectado' => true,
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    private function criarAgendamentoAmanha(array $overrides = []): Agendamento
    {
        return Agendamento::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'cliente_nome' => 'Cliente Teste',
            'cliente_telefone' => '51999999999',
            'inicio' => now()->addDay()->setTime(10, 0),
            'fim' => now()->addDay()->setTime(10, 30),
            'data_hora' => now()->addDay()->setTime(10, 0),
            'duracao_minutos' => 30,
            'status' => 'agendado',
            'origem' => 'manual',
            'lembrete_enviado' => false,
        ], $overrides));
    }

    public function test_envia_lembrete_e_marca_como_enviado(): void
    {
        $agendamento = $this->criarAgendamentoAmanha();

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('enviarMensagem')->once()->andReturn(true);
        });

        EnviarLembretesJob::dispatchSync();

        $this->assertTrue($agendamento->fresh()->lembrete_enviado);
    }

    public function test_nao_reenvia_lembrete_ja_enviado(): void
    {
        $this->criarAgendamentoAmanha(['lembrete_enviado' => true]);

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldNotReceive('enviarMensagem');
        });

        EnviarLembretesJob::dispatchSync();
    }

    public function test_ignora_tenant_sem_whatsapp_conectado(): void
    {
        $this->tenant->update(['whatsapp_conectado' => false]);
        $agendamento = $this->criarAgendamentoAmanha();

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldNotReceive('enviarMensagem');
        });

        EnviarLembretesJob::dispatchSync();

        $this->assertFalse($agendamento->fresh()->lembrete_enviado);
    }

    public function test_respeita_configuracao_lembrete_ativo_false(): void
    {
        $this->tenant->update(['configuracoes' => ['lembrete_ativo' => false]]);
        $agendamento = $this->criarAgendamentoAmanha();

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldNotReceive('enviarMensagem');
        });

        EnviarLembretesJob::dispatchSync();

        $this->assertFalse($agendamento->fresh()->lembrete_enviado);
    }
}
