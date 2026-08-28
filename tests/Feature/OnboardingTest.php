<?php

namespace Tests\Feature;

use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function dadosStep1(array $overrides = []): array
    {
        return array_merge([
            'nome_usuario' => 'Dono Teste',
            'email' => 'dono@example.com',
            'senha' => 'senha12345',
            'senha_confirmation' => 'senha12345',
            'nome_estabelecimento' => 'Barbearia Onboarding',
            'tipo_servico' => 'barbeiro',
            'telefone' => '51999999999',
        ], $overrides);
    }

    public function test_fluxo_feliz_completo(): void
    {
        Queue::fake();

        $response = $this->post('/cadastro', $this->dadosStep1());
        $response->assertRedirect(route('onboarding.step3'));
        Queue::assertPushed(CreateEvolutionInstanceJob::class);

        $tenant = Tenant::where('slug', 'like', 'barbearia-onboarding-%')->firstOrFail();
        $this->assertDatabaseHas('tenant_users', ['tenant_id' => $tenant->id]);

        $this->assertSame('starter', $tenant->fresh()->plano);
        $this->assertSame('51999999999', $tenant->fresh()->telefone_whatsapp);
        $this->assertNull($tenant->fresh()->asaas_subscription_id);

        $response = $this->post(route('onboarding.step3.store'), [
            'nome_item' => 'Dono Teste',
            'nome_servico' => 'Corte',
            'duracao_minutos' => 30,
            'valor' => 45,
            'dias_atendimento' => 'segunda_sabado',
            'hora_abertura' => '09:00',
            'hora_fechamento' => '18:00',
            'perfil_regras' => 'equilibrado',
        ]);
        $response->assertRedirect(route('onboarding.sucesso'));
        $fresh = $tenant->fresh();
        $this->assertSame('Assistente da Barbearia Onboarding', $fresh->nome_agente);
        $this->assertSame('Olá! Bem-vindo à Barbearia Onboarding. Como posso ajudar?', $fresh->bot_saudacao);
        $this->assertNull($fresh->instrucoes_extras);
        $this->assertTrue($fresh->bot_ativo);
        $this->assertSame(30, $fresh->regrasAgendamentoConfig()['antecedencia_minima_minutos']);
        $this->assertSame(30, $fresh->regrasAgendamentoConfig()['antecedencia_maxima_dias']);

        $this->assertDatabaseHas('profissionais', [
            'tenant_id' => $tenant->id,
            'nome' => 'Dono Teste',
        ]);
        $this->assertDatabaseHas('servicos', [
            'tenant_id' => $tenant->id,
            'nome' => 'Corte',
            'duracao_minutos' => 30,
        ]);
        $this->assertDatabaseCount('horarios_profissional', 6);

        $this->get(route('onboarding.sucesso'))->assertOk();
    }

    public function test_configuracao_expressa_cria_recurso_para_quadra(): void
    {
        Queue::fake();
        $this->post('/cadastro', $this->dadosStep1([
            'nome_estabelecimento' => 'Arena Express',
            'tipo_servico' => 'quadra',
        ]));

        $this->post(route('onboarding.step3.store'), [
            'nome_item' => 'Quadra de futsal',
            'nome_servico' => 'Reserva',
            'duracao_minutos' => 60,
            'valor' => 120,
            'dias_atendimento' => 'todos',
            'hora_abertura' => '07:00',
            'hora_fechamento' => '22:00',
            'perfil_regras' => 'protegido',
        ])->assertRedirect(route('onboarding.sucesso'));

        $tenant = Tenant::where('slug', 'like', 'arena-express-%')->firstOrFail();
        $this->assertDatabaseHas('recursos', [
            'tenant_id' => $tenant->id,
            'nome' => 'Quadra de futsal',
            'duracao_padrao_minutos' => 60,
        ]);
        $this->assertDatabaseCount('horarios_funcionamento', 7);
        $this->assertSame(15, $tenant->regrasAgendamentoConfig()['buffer_entre_agendamentos_minutos']);
    }

    public function test_configuracao_expressa_usa_estabelecimento_selecionado_na_sessao(): void
    {
        $user = User::factory()->create(['name' => 'Carlos']);
        $odonto = Tenant::create([
            'nome' => 'Odonto Teste',
            'slug' => 'odonto-teste',
            'tipo_servico' => 'clinica',
            'ativo' => true,
        ]);
        $society = Tenant::create([
            'nome' => 'Society Teste',
            'slug' => 'society-onboarding-teste',
            'tipo_servico' => 'quadra',
            'ativo' => true,
        ]);
        $odonto->users()->attach($user->id, ['papel' => 'admin']);
        $society->users()->attach($user->id, ['papel' => 'admin']);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $society->id])
            ->get(route('onboarding.step3'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenant.nome', 'Society Teste')
                ->where('tenant.tipo_servico', 'quadra')
                ->where('defaults.nome_item', 'Quadra principal'));

        $this->actingAs($user)
            ->withSession(['tenant_id' => $society->id])
            ->post(route('onboarding.step3.store'), [
                'nome_item' => 'Quadra Society',
                'nome_servico' => 'Reserva',
                'duracao_minutos' => 60,
                'valor' => 120,
                'dias_atendimento' => 'todos',
                'hora_abertura' => '08:00',
                'hora_fechamento' => '23:00',
                'perfil_regras' => 'equilibrado',
            ])
            ->assertRedirect(route('onboarding.sucesso'));

        $this->assertDatabaseHas('recursos', [
            'tenant_id' => $society->id,
            'nome' => 'Quadra Society',
        ]);
        $this->assertDatabaseMissing('recursos', [
            'tenant_id' => $odonto->id,
            'nome' => 'Quadra Society',
        ]);
        $this->assertDatabaseMissing('profissionais', [
            'tenant_id' => $society->id,
            'nome' => 'Carlos',
        ]);
    }

    public function test_step1_store_impede_email_duplicado(): void
    {
        Queue::fake();
        $this->post('/cadastro', $this->dadosStep1());

        $this->post('/cadastro', $this->dadosStep1(['nome_estabelecimento' => 'Outra Barbearia']))
            ->assertSessionHasErrors('email');
    }

    public function test_pular_pagamento_redireciona_step3(): void
    {
        Queue::fake();
        $this->post('/cadastro', $this->dadosStep1());

        $this->post(route('onboarding.pular'))
            ->assertRedirect(route('onboarding.step3'));
    }

    public function test_step1_normaliza_email_e_whatsapp_formatado(): void
    {
        Queue::fake();

        $this->post('/cadastro', $this->dadosStep1([
            'email' => ' DONO.NORMALIZADO@EXAMPLE.COM ',
            'telefone' => '(51) 99999-9999',
        ]))->assertRedirect(route('onboarding.step3'));

        $tenant = Tenant::where('slug', 'like', 'barbearia-onboarding-%')->firstOrFail();
        $this->assertSame('51999999999', $tenant->telefone_whatsapp);
        $this->assertDatabaseHas('users', [
            'email' => 'dono.normalizado@example.com',
            'telefone' => '51999999999',
        ]);
    }
}
