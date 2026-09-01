<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Conversa;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfissionalEscopoAcessoTest extends TestCase
{
    use RefreshDatabase;

    public function test_profissional_so_enxerga_e_altera_os_proprios_agendamentos(): void
    {
        [$tenant, $profissional, $outroProfissional, $user] = $this->criarCenario();
        $meuAgendamento = $this->agendamento($tenant, $profissional, 'Cliente Meu');
        $agendamentoDeOutro = $this->agendamento($tenant, $outroProfissional, 'Cliente Outro');

        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->get(route('tenant.agendamentos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('agendamentos.data', 1)
                ->where('agendamentos.data.0.id', $meuAgendamento->id)
                ->has('profissionais', 1)
                ->where('profissionais.0.id', $profissional->id));

        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->patch(route('tenant.agendamentos.cancelar', $agendamentoDeOutro))
            ->assertForbidden();
    }

    public function test_profissional_nao_acessa_conversa_de_outro_profissional(): void
    {
        [$tenant, $profissional, $outroProfissional, $user] = $this->criarCenario();
        $minhaConversa = Conversa::create([
            'tenant_id' => $tenant->id,
            'profissional_id' => $profissional->id,
            'telefone_cliente' => '5551999990001',
            'status_v2' => 'em_atendimento_humano',
        ]);
        $conversaDeOutro = Conversa::create([
            'tenant_id' => $tenant->id,
            'profissional_id' => $outroProfissional->id,
            'telefone_cliente' => '5551999990002',
            'status_v2' => 'em_atendimento_humano',
        ]);

        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->post(route('tenant.conversas.assumir', $minhaConversa))
            ->assertRedirect();

        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->get(route('tenant.conversas.mensagens', $conversaDeOutro))
            ->assertForbidden();
    }

    private function criarCenario(): array
    {
        $tenant = Tenant::create([
            'nome' => 'Barbearia Escopo',
            'slug' => 'barbearia-escopo',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['papel' => 'profissional', 'ativo' => true]);
        $profissional = Profissional::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'nome' => 'João', 'ativo' => true]);
        $outroProfissional = Profissional::create(['tenant_id' => $tenant->id, 'nome' => 'Pedro', 'ativo' => true]);

        return [$tenant, $profissional, $outroProfissional, $user];
    }

    private function agendamento(Tenant $tenant, Profissional $profissional, string $cliente): Agendamento
    {
        return Agendamento::create([
            'tenant_id' => $tenant->id,
            'profissional_id' => $profissional->id,
            'cliente_nome' => $cliente,
            'cliente_telefone' => '555199999'.str_pad((string) $profissional->id, 4, '0', STR_PAD_LEFT),
            'inicio' => now()->addDay(),
            'fim' => now()->addDay()->addMinutes(30),
            'data_hora' => now()->addDay(),
            'duracao_minutos' => 30,
            'status' => 'confirmado',
            'origem' => 'manual',
        ]);
    }
}
