<?php

namespace Tests\Feature;

use App\Exceptions\EvolutionApiException;
use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\Cliente;
use App\Models\OpcaoExtra;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EvolutionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QaExploratoryRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'nome' => 'Tenant QA',
            'slug' => 'tenant-qa-'.uniqid(),
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ], $overrides));
    }

    private function tenantAdmin(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['papel' => 'admin']);

        return $user;
    }

    private function actingAsTenantAdmin(Tenant $tenant, ?User $user = null): static
    {
        return $this->actingAs($user ?? $this->tenantAdmin($tenant))
            ->withSession([
                'tenant_id' => $tenant->id,
                'auth.password_confirmed_at' => time(),
            ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    public function test_opcoes_extras_page_exists_and_is_scoped_to_the_current_tenant(): void
    {
        $tenant = $this->tenant();
        $outroTenant = $this->tenant(['nome' => 'Outro tenant']);
        OpcaoExtra::create([
            'tenant_id' => $tenant->id,
            'tipo' => 'pagamento',
            'nome' => 'Pix',
            'ativo' => true,
        ]);
        OpcaoExtra::create([
            'tenant_id' => $outroTenant->id,
            'tipo' => 'pagamento',
            'nome' => 'Boleto externo',
            'ativo' => true,
        ]);

        $response = $this->actingAsTenantAdmin($tenant)
            ->get(route('tenant.opcoes-extras.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/OpcaoExtra/Index')
                ->has('opcoes', 1)
                ->where('opcoes.0.nome', 'Pix'));
    }

    public function test_superadmin_agendamentos_page_exists(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get(route('superadmin.agendamentos'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Agendamentos')
                ->has('agendamentos.data', 0));
    }

    public function test_agendamento_manual_exposes_profissionais_servicos_and_prefills_scoped_client(): void
    {
        $tenant = $this->tenant();
        $profissional = $tenant->profissionais()->create([
            'nome' => 'Dra. QA',
            'ativo' => true,
        ]);
        $servico = $tenant->servicos()->create([
            'nome' => 'Consulta QA',
            'duracao_minutos' => 45,
            'ativo' => true,
        ]);
        $servico->profissionais()->attach($profissional->id);
        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Cliente de origem',
            'telefone' => '5554999991234',
        ]);

        $response = $this->actingAsTenantAdmin($tenant)
            ->get(route('tenant.agendamentos.index', [
                'novo' => 1,
                'cliente' => $cliente->telefone,
            ]));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('profissionais', 1)
                ->where('profissionais.0.nome', 'Dra. QA')
                ->has('servicos', 1)
                ->where('servicos.0.nome', 'Consulta QA')
                ->where('servicos.0.profissional_ids.0', $profissional->id)
                ->where('clienteInicial.nome', 'Cliente de origem')
                ->where('clienteInicial.telefone', '5554999991234'));
    }

    public function test_agendamento_manual_never_prefills_client_from_another_tenant(): void
    {
        $tenant = $this->tenant();
        $clienteExterno = Cliente::create([
            'tenant_id' => $this->tenant(['nome' => 'Tenant externo'])->id,
            'nome' => 'Cliente externo',
            'telefone' => '5554999995678',
        ]);

        $response = $this->actingAsTenantAdmin($tenant)
            ->get(route('tenant.agendamentos.index', [
                'novo' => 1,
                'cliente' => $clienteExterno->telefone,
            ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('clienteInicial', null));
    }

    public function test_superadmin_confirms_password_before_filling_tenant_form(): void
    {
        $route = app('router')->getRoutes()->getByName('superadmin.tenants.create');

        $this->assertNotNull($route);
        $this->assertContains('password.confirm', $route->gatherMiddleware());
    }

    public function test_superadmin_tenant_creation_queues_whatsapp_setup(): void
    {
        Queue::fake();
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('superadmin.tenants.store'), [
                'nome' => 'Tenant criado em QA',
                'tipo_servico' => 'clinica',
                'email_dono' => 'dono-regressao@example.test',
                'senha_dono' => 'senha-segura-123',
            ]);

        $response->assertRedirect(route('superadmin.tenants.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tenants', ['nome' => 'Tenant criado em QA']);
        $this->assertDatabaseHas('users', ['email' => 'dono-regressao@example.test']);
        Queue::assertPushed(CreateEvolutionInstanceJob::class);
    }

    public function test_qrcode_returns_operational_error_when_evolution_is_not_configured(): void
    {
        config(['services.evolution.url' => '']);
        $tenant = $this->tenant(['evolution_instance' => 'tenant-qa-whatsapp']);

        $response = $this->actingAsTenantAdmin($tenant)
            ->getJson(route('tenant.whatsapp.qrcode'));

        $response->assertStatus(503)
            ->assertJson([
                'erro' => 'Não foi possível conectar ao WhatsApp agora. Tente novamente em alguns minutos.',
            ]);
    }

    public function test_evolution_instance_creation_raises_domain_error_on_http_failure(): void
    {
        config([
            'services.evolution.url' => 'https://evolution.example.test',
            'services.evolution.key' => 'test-key',
        ]);
        Http::fake([
            'evolution.example.test/*' => Http::response(['message' => 'falha'], 500),
        ]);

        $this->expectException(EvolutionApiException::class);

        app(EvolutionApiService::class)->criarInstancia('tenant-qa');
    }

    public function test_logout_clears_inertia_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/')
            ->assertSessionHas('inertia.clear_history', true);

        $this->assertGuest();
    }
}
