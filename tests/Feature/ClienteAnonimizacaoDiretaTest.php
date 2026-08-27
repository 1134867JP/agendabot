<?php

namespace Tests\Feature;

use App\Http\Middleware\ConfirmPassword;
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

    public function test_anonimizacao_http_sem_password_confirm(): void
    {
        $this->withoutMiddleware(ConfirmPassword::class);

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

        fwrite(STDERR, "[sem-password] antes DELETE HTTP\n");
        $response = $this->actingAs($user)->withSession([
            'tenant_id' => $tenant->id,
        ])->delete(route('tenant.clientes.destroy', $cliente));
        fwrite(STDERR, "[sem-password] depois DELETE HTTP\n");

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
