<?php

namespace Tests\Feature;

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

    public function test_rota_real_sem_middlewares_anonimiza_duas_conversas(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Clínica Diagnóstico',
            'slug' => 'clinica-diagnostico-sem-middleware',
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

        $outraConversa = Conversa::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'telefone_cliente' => $cliente->telefone.'-alternativo',
            'status_v2' => 'ativa',
        ]);
        $outraMensagem = $outraConversa->registrarMensagem('cliente', 'Outra mensagem preservada');

        fwrite(STDERR, "[sem-middleware] antes DELETE\n");
        $response = $this->actingAs($user)->delete(route('tenant.clientes.destroy', $cliente->id));
        fwrite(STDERR, "[sem-middleware] depois DELETE\n");

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
        $this->assertDatabaseHas('conversas', [
            'id' => $outraConversa->id,
            'cliente_id' => null,
            'telefone_cliente' => "anonimizado-{$cliente->id}-{$outraConversa->id}",
            'status_v2' => 'encerrada',
        ]);
        $this->assertDatabaseHas('mensagens', ['id' => $mensagem->id]);
        $this->assertDatabaseHas('mensagens', ['id' => $outraMensagem->id]);
        $this->assertSame(2, Mensagem::count());
    }
}
