<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConversaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgendamentoConfirmadoCancelamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_agendamento_ja_confirmado_continua_encontravel_para_cancelar(): void
    {
        // Um agendamento confirmado (status='confirmado', não mais 'agendado') precisa
        // continuar sendo encontrado como "pendente" pelo job — senão o bot diz "não
        // encontrei nenhum agendamento" e transfere o cliente para um humano, mesmo ele
        // tendo um agendamento ativo.
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Barbearia Confirmada',
            'slug' => 'barbearia-confirmada',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'bot_ativo' => true,
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($user->id, ['papel' => 'admin']);

        $profissional = Profissional::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Barbeiro Teste',
            'ativo' => true,
        ]);

        $telefone = '5551988000001';
        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'telefone' => $telefone,
            'nome' => 'Cliente Confirmado',
        ]);

        Agendamento::create([
            'tenant_id' => $tenant->id,
            'profissional_id' => $profissional->id,
            'cliente_id' => $cliente->id,
            'cliente_nome' => $cliente->nome,
            'cliente_telefone' => $telefone,
            'inicio' => now()->addDay(),
            'fim' => now()->addDay()->addMinutes(30),
            'data_hora' => now()->addDay(),
            'duracao_minutos' => 30,
            'status' => 'confirmado',
            'origem' => 'bot',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Sem problemas, já cancelo pra você!']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);

        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $tenant, $telefone, 'quero desmarcar', 'MSG_CONFIRMADO_1', 'Cliente Confirmado'
        );
        ProcessarMensagemWhatsapp::dispatchSync($tenant, $telefone, $mensagem->id);

        $requisicaoClaude = Http::recorded(fn ($request) => str_contains($request->url(), 'anthropic.com'))->first();
        $systemPrompt = collect($requisicaoClaude[0]->data()['system'])->pluck('text')->join("\n");

        $this->assertStringContainsString('PENDENTE', $systemPrompt);

        $conversa = Conversa::where('tenant_id', $tenant->id)->where('telefone_cliente', $telefone)->first();
        $this->assertNotSame('aguardando_humano', $conversa->status_v2);
    }
}
