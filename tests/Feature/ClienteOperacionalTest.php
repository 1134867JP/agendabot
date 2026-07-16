<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteOperacionalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome' => 'Clínica Clientes',
            'slug' => 'clinica-clientes',
            'tipo_servico' => 'clinica',
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

    public function test_busca_retorna_apenas_clientes_do_tenant(): void
    {
        Cliente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'João da Silva',
            'telefone' => '5554999999999',
        ]);

        $outroTenant = Tenant::create([
            'nome' => 'Outra clínica',
            'slug' => 'outra-clinica-clientes',
            'tipo_servico' => 'clinica',
            'ativo' => true,
        ]);
        Cliente::create([
            'tenant_id' => $outroTenant->id,
            'nome' => 'João de Outro Tenant',
            'telefone' => '5554888888888',
        ]);

        $response = $this->autenticarComTenant()->getJson(route('tenant.clientes.search', ['q' => 'João']));

        $response->assertOk()
            ->assertJsonCount(1, 'clientes')
            ->assertJsonPath('clientes.0.nome', 'João da Silva');
    }

    public function test_anonimizacao_preserva_conversa_e_mensagens(): void
    {
        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Cliente Teste',
            'telefone' => '5554999999999',
        ]);
        $conversa = Conversa::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'telefone_cliente' => $cliente->telefone,
            'status_v2' => 'ativa',
        ]);
        $mensagem = $conversa->registrarMensagem('cliente', 'Mensagem preservada');

        $response = $this->autenticarComTenant()->delete(route('tenant.clientes.destroy', $cliente));

        $response->assertRedirect(route('tenant.clientes.index'));
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nome' => 'Cliente anonimizado',
            'telefone' => "anonimizado-{$cliente->id}",
        ]);
        $this->assertDatabaseHas('conversas', [
            'id' => $conversa->id,
            'cliente_id' => null,
            'telefone_cliente' => "anonimizado-{$cliente->id}",
            'status_v2' => 'encerrada',
        ]);
        $this->assertDatabaseHas('mensagens', ['id' => $mensagem->id]);
        $this->assertSame(1, Mensagem::count());
    }
}
