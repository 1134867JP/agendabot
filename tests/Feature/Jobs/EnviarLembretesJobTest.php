<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EnviarLembretesJob;
use App\Models\Agendamento;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EvolutionApiService;
use Carbon\Carbon;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Agendamento marcado ontem para as 11:30 de "hoje", com o relógio às 10:00:
     * dentro da janela padrão de 2h da barbearia.
     */
    private function criarAgendamentoAmanha(array $overrides = [], string $agora = '2026-10-14 10:00:00', string $horario = '2026-10-14 11:30:00', ?string $marcadoEm = '2026-10-13 18:00:00'): Agendamento
    {
        Carbon::setTestNow(Carbon::parse($agora, 'America/Sao_Paulo'));
        $inicio = Carbon::parse($horario, 'America/Sao_Paulo');

        $agendamento = Agendamento::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'cliente_nome' => 'Cliente Teste',
            'cliente_telefone' => '51999999999',
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'data_hora' => $inicio,
            'duracao_minutos' => 30,
            'status' => 'agendado',
            'origem' => 'manual',
            'lembrete_enviado' => false,
        ], $overrides));

        if ($marcadoEm) {
            $agendamento->created_at = Carbon::parse($marcadoEm, 'America/Sao_Paulo');
            $agendamento->save();
        }

        return $agendamento;
    }

    public function test_envia_lembrete_e_marca_como_enviado(): void
    {
        $agendamento = $this->criarAgendamentoAmanha();

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('enviarMensagem')
                ->once()
                ->withArgs(fn ($instancia, $telefone, $texto) => str_contains($texto, '*hoje*') && str_contains($texto, '11:30'))
                ->andReturn(true);
        });

        EnviarLembretesJob::dispatchSync();

        $this->assertTrue($agendamento->fresh()->lembrete_enviado);
    }

    public function test_barbearia_nao_lembra_antes_da_janela_de_2h(): void
    {
        $agendamento = $this->criarAgendamentoAmanha(horario: '2026-10-14 15:00:00');

        $this->mock(EvolutionApiService::class, fn ($mock) => $mock->shouldNotReceive('enviarMensagem'));

        EnviarLembretesJob::dispatchSync();

        $this->assertFalse($agendamento->fresh()->lembrete_enviado);
    }

    public function test_respeita_antecedencia_configurada_pelo_estabelecimento(): void
    {
        $this->tenant->update(['configuracoes' => ['lembrete_horas_antes' => 24]]);
        $agendamento = $this->criarAgendamentoAmanha(horario: '2026-10-15 09:00:00');

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('enviarMensagem')
                ->once()
                ->withArgs(fn ($instancia, $telefone, $texto) => str_contains($texto, '*amanhã*'))
                ->andReturn(true);
        });

        EnviarLembretesJob::dispatchSync();

        $this->assertTrue($agendamento->fresh()->lembrete_enviado);
    }

    public function test_nao_envia_no_horario_de_silencio(): void
    {
        $agendamento = $this->criarAgendamentoAmanha(agora: '2026-10-14 07:00:00', horario: '2026-10-14 08:30:00');

        $this->mock(EvolutionApiService::class, fn ($mock) => $mock->shouldNotReceive('enviarMensagem'));

        EnviarLembretesJob::dispatchSync();

        $this->assertFalse($agendamento->fresh()->lembrete_enviado);
    }

    public function test_nao_lembra_quem_acabou_de_marcar(): void
    {
        $agendamento = $this->criarAgendamentoAmanha(marcadoEm: '2026-10-14 09:30:00');

        $this->mock(EvolutionApiService::class, fn ($mock) => $mock->shouldNotReceive('enviarMensagem'));

        EnviarLembretesJob::dispatchSync();

        $this->assertFalse($agendamento->fresh()->lembrete_enviado);
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
