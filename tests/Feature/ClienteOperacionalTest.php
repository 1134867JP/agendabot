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
        return $this->actingAs($this->user)->withSession([
            'tenant_id' => $this->tenant->id,
            'auth.password_confirmed_at' => now()->timestamp,
        ]);
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
        $outraConversa = Conversa::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'telefone_cliente' => $cliente->telefone.'-alternativo',
            'status_v2' => 'ativa',
        ]);
        $outraMensagem = $outraConversa->registrarMensagem('cliente', 'Outra mensagem preservada');

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

    public function test_anonimizacao_em_massa_processa_todo_o_lote(): void
    {
        $primeiro = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Primeiro cliente',
            'telefone' => '5554999999991',
        ]);
        $segundo = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Segundo cliente',
            'telefone' => '5554999999992',
        ]);

        $response = $this->autenticarComTenant()->delete(route('tenant.clientes.destroy-bulk'), [
            'cliente_ids' => [$primeiro->id, $segundo->id],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', [
            'id' => $primeiro->id,
            'nome' => 'Cliente anonimizado',
            'telefone' => "anonimizado-{$primeiro->id}",
        ]);
        $this->assertDatabaseHas('clientes', [
            'id' => $segundo->id,
            'nome' => 'Cliente anonimizado',
            'telefone' => "anonimizado-{$segundo->id}",
        ]);
    }

    public function test_anonimizacao_em_massa_rejeita_ids_de_outro_tenant_sem_processar_parcialmente(): void
    {
        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Cliente local',
            'telefone' => '5554999999993',
        ]);
        $outroTenant = Tenant::create([
            'nome' => 'Tenant externo',
            'slug' => 'tenant-externo-clientes',
            'tipo_servico' => 'clinica',
            'ativo' => true,
        ]);
        $clienteExterno = Cliente::create([
            'tenant_id' => $outroTenant->id,
            'nome' => 'Cliente externo',
            'telefone' => '5554999999994',
        ]);

        $response = $this->autenticarComTenant()->delete(route('tenant.clientes.destroy-bulk'), [
            'cliente_ids' => [$cliente->id, $clienteExterno->id],
        ]);

        $response->assertSessionHasErrors('cliente_ids.1');
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nome' => 'Cliente local',
            'telefone' => '5554999999993',
        ]);
        $this->assertDatabaseHas('clientes', [
            'id' => $clienteExterno->id,
            'nome' => 'Cliente externo',
            'telefone' => '5554999999994',
        ]);
    }
}
