<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriagemControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Triagem',
            'slug' => 'barbearia-triagem',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'horario_atendimento' => [
                0 => ['ativo' => false, 'abertura' => '08:00', 'fechamento' => '18:00'],
                1 => ['ativo' => true, 'abertura' => '08:00', 'fechamento' => '18:00'],
                2 => ['ativo' => true, 'abertura' => '08:00', 'fechamento' => '18:00'],
                3 => ['ativo' => true, 'abertura' => '08:00', 'fechamento' => '18:00'],
                4 => ['ativo' => true, 'abertura' => '08:00', 'fechamento' => '18:00'],
                5 => ['ativo' => true, 'abertura' => '08:00', 'fechamento' => '18:00'],
                6 => ['ativo' => false, 'abertura' => '08:00', 'fechamento' => '18:00'],
            ],
        ]);
        $this->tenant->users()->attach($this->user->id, ['papel' => 'admin']);
    }

    private function autenticarComTenant()
    {
        return $this->actingAs($this->user)->withSession(['tenant_id' => $this->tenant->id]);
    }

    public function test_index_retorna_config_com_defaults_quando_nada_foi_salvo(): void
    {
        $response = $this->autenticarComTenant()->get(route('tenant.triagem.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tenant/Triagem')
            ->where('config.max_tentativas_sem_entender', 2)
            ->where('config.palavras_chave_humano', [])
            ->where('horarioFuncionamento.configurado', true)
            ->where('horarioFuncionamento.resumo', 'Seg–Sex 08:00–18:00')
        );
    }

    public function test_update_persiste_regras_de_triagem(): void
    {
        $response = $this->autenticarComTenant()->put(route('tenant.triagem.update'), [
            'palavras_chave_humano' => [' Atendente ', 'ATENDENTE', 'humano'],
            'max_tentativas_sem_entender' => 3,
            'transferir_fora_do_horario' => true,
            'mensagem_transferencia' => 'Já te transfiro!',
        ]);

        $response->assertRedirect();

        $this->tenant->refresh();
        $this->assertSame([
            'palavras_chave_humano' => ['atendente', 'humano'],
            'max_tentativas_sem_entender' => 3,
            'transferir_fora_do_horario' => true,
            'mensagem_transferencia' => 'Já te transfiro!',
        ], $this->tenant->triagemConfig());
    }

    public function test_update_rejeita_max_tentativas_fora_do_intervalo(): void
    {
        $response = $this->autenticarComTenant()->put(route('tenant.triagem.update'), [
            'palavras_chave_humano' => [],
            'max_tentativas_sem_entender' => 0,
        ]);

        $response->assertSessionHasErrors('max_tentativas_sem_entender');
    }
}
