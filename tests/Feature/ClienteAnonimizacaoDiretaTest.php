<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\ClienteController;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteAnonimizacaoDiretaTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_anonimiza_sem_pipeline_http(): void
    {
        $tenant = Tenant::create([
            'nome' => 'Clínica Clientes Direto',
            'slug' => 'clinica-clientes-direto',
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
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

        fwrite(STDERR, "[direto] antes controller\n");
        $response = app(ClienteController::class)->destroy($cliente);
        fwrite(STDERR, "[direto] depois controller\n");

        $this->assertSame(302, $response->getStatusCode());
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
