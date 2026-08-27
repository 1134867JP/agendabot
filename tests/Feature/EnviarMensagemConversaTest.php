<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\ConversaController;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnviarMensagemConversaTest extends TestCase
{
    use RefreshDatabase;

    public function test_enviar_mensagem_com_bot_ativo_assume_e_envia_no_mesmo_request(): void
    {
        $tenant = Tenant::create([
            'nome' => 'Clínica Teste',
            'slug' => 'clinica-teste',
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        app()->instance('tenant', $tenant);

        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Mariana',
            'telefone' => '5554999999999',
        ]);
        $conversa = Conversa::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'telefone_cliente' => $cliente->telefone,
            'status_v2' => 'ativa',
        ]);

        $evolution = $this->mock(EvolutionApiService::class);
        $evolution->shouldReceive('enviarMensagem')
            ->once()
            ->with('instancia-teste', $cliente->telefone, 'Quero confirmar o horário')
            ->andReturnTrue();

        $request = Request::create('/', 'POST', [
            'conteudo' => 'Quero confirmar o horário',
        ]);

        (new ConversaController())->enviarMensagem($request, $conversa, $evolution);

        $this->assertSame('em_atendimento_humano', $conversa->fresh()->status_v2);
        $this->assertDatabaseHas('mensagens', [
            'conversa_id' => $conversa->id,
            'remetente' => 'humano',
            'conteudo' => 'Quero confirmar o horário',
        ]);
        $this->assertSame(0, $conversa->mensagens()->where('remetente', 'bot')->count());
    }
}
