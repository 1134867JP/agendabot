<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\ConfirmPassword;
use App\Http\Middleware\EnsureHasTenant;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteAnonimizacaoDiretaTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonimizacao_http_sem_middlewares_customizados(): void
    {
        $this->withoutMiddleware([
            ConfirmPassword::class,
            EnsureHasTenant::class,
            EnsureTenantAdmin::class,
            CheckSubscription::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);

        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Clínica Clientes Diagnóstico',
            'slug' => 'clinica-clientes-diagnostico',
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($user->id, ['papel' => 'admin']);
        app()->instance('tenant', $tenant);

        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Cliente Teste',
            'telefone' => '5554999999999',
        ]);
        $conversa = Conversa::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'telefone_cliente' => $cliente->telefone,
            'status_v2' => 'ativa',
        ]);
        $mensagem = $conversa->registrarMensagem('cliente', 'Mensagem preservada');

        fwrite(STDERR, "[sem-custom] antes DELETE HTTP\n");
        $response = $this->actingAs($user)->withSession([
            'tenant_id' => $tenant->id,
        ])->delete(route('tenant.clientes.destroy', $cliente));
        fwrite(STDERR, "[sem-custom] depois DELETE HTTP\n");

        $response->assertRedirect(route('tenant.clientes.index'));
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nome' => 'Cliente anonimizado',
            'telefone' => "anonimizado-{$cliente->id}",
        ]);
        $this->assertDatabaseHas('conversas', [
            'id' => $conversa->id,
            'cliente_id' => null,
            'telefone_cliente' => "anonimizado-{$cliente->id}-{$conversa->id}",
            'status_v2' => 'encerrada',
        ]);
        $this->assertDatabaseHas('mensagens', ['id' => $mensagem->id]);
        $this->assertSame(1, Mensagem::count());
    }
}
