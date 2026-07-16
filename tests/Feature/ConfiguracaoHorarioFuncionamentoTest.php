<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracaoHorarioFuncionamentoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Estabelecimento Horários',
            'slug' => 'estabelecimento-horarios',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);
    }

    private function autenticarComTenant()
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }

    public function test_salva_grade_semanal_e_gera_resumo_para_o_bot(): void
    {
        $horarios = $this->semanaFechada();
        foreach ([1, 2, 3, 4, 5] as $dia) {
            $horarios[$dia] = [
                'ativo' => true,
                'periodos' => [
                    ['abertura' => '09:00', 'fechamento' => '12:00'],
                    ['abertura' => '13:30', 'fechamento' => '18:00'],
                ],
            ];
        }
        $horarios[6] = [
            'ativo' => true,
            'periodos' => [['abertura' => '09:00', 'fechamento' => '13:00']],
        ];

        $response = $this->autenticarComTenant()->put(route('tenant.configuracoes.update'), [
            'nome' => $this->tenant->nome,
            'tipo_servico' => 'barbeiro',
            'tipo_servico_personalizado' => null,
            'horarios_funcionamento_semana' => $horarios,
        ]);

        $response->assertSessionDoesntHaveErrors();

        $tenant = $this->tenant->fresh();
        $this->assertSame(
            'Seg–Sex 09:00–12:00 e 13:30–18:00, Sáb 09:00–13:00',
            $tenant->horarios_funcionamento
        );
        $this->assertSame($horarios, $tenant->configuracoes['horarios_funcionamento_semana']);
    }

    public function test_rejeita_periodos_sobrepostos(): void
    {
        $horarios = $this->semanaFechada();
        $horarios[1] = [
            'ativo' => true,
            'periodos' => [
                ['abertura' => '09:00', 'fechamento' => '12:00'],
                ['abertura' => '11:30', 'fechamento' => '14:00'],
            ],
        ];

        $response = $this->autenticarComTenant()->put(route('tenant.configuracoes.update'), [
            'nome' => $this->tenant->nome,
            'tipo_servico' => 'barbeiro',
            'tipo_servico_personalizado' => null,
            'horarios_funcionamento_semana' => $horarios,
        ]);

        $response->assertSessionHasErrors('horarios_funcionamento_semana.1.periodos.1.abertura');
    }

    private function semanaFechada(): array
    {
        return array_fill(0, 7, [
            'ativo' => false,
            'periodos' => [['abertura' => '09:00', 'fechamento' => '18:00']],
        ]);
    }
}
