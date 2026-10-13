<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsConversaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_que_volta_varias_vezes_nao_faz_a_conversao_passar_de_100(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Barbearia Métricas',
            'slug' => 'barbearia-metricas',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'plano' => 'pro',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($user->id, ['papel' => 'admin']);

        // Cliente fiel: conversa antiga, três marcações pelo bot neste mês.
        $fiel = Cliente::create(['tenant_id' => $tenant->id, 'telefone' => '5551999990001', 'nome' => 'Fiel']);
        $conversa = Conversa::create(['tenant_id' => $tenant->id, 'cliente_id' => $fiel->id, 'telefone_cliente' => $fiel->telefone, 'status_v2' => 'ativa']);
        $conversa->forceFill(['created_at' => now()->subMonths(6)])->save();
        $conversa->registrarMensagem('cliente', 'Oi, tem horário?');

        // Só perguntou o preço.
        $curioso = Cliente::create(['tenant_id' => $tenant->id, 'telefone' => '5551999990002', 'nome' => 'Curioso']);
        Conversa::create(['tenant_id' => $tenant->id, 'cliente_id' => $curioso->id, 'telefone_cliente' => $curioso->telefone, 'status_v2' => 'ativa'])
            ->registrarMensagem('cliente', 'Quanto é o corte?');

        foreach ([1, 3, 5] as $dias) {
            $inicio = now()->addDays($dias)->setTime(10, 0);
            Agendamento::create([
                'tenant_id' => $tenant->id, 'cliente_id' => $fiel->id, 'cliente_nome' => 'Fiel', 'cliente_telefone' => $fiel->telefone,
                'inicio' => $inicio, 'fim' => $inicio->copy()->addMinutes(30), 'data_hora' => $inicio, 'duracao_minutos' => 30,
                'status' => 'agendado', 'origem' => 'bot',
            ]);
        }

        $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])
            ->get(route('tenant.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.conversas_mes', 2)
                ->where('stats.taxa_conversao', 50));
    }
}
