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

    public function test_mensagem_apos_agendamento_mantem_reserva_e_nao_disponibiliza_criacao(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'nome' => 'Barbearia Agradecimento',
            'slug' => 'barbearia-agradecimento',
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
            'nome' => 'Asafe',
            'ativo' => true,
        ]);
        $telefone = '5551988000002';
        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'telefone' => $telefone,
            'nome' => 'João',
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
            'status' => 'agendado',
            'origem' => 'bot',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Pode deixar, João!']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);
        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $tenant, $telefone, 'E se eu precisar trocar o horário?', 'MSG_POS_AGENDAMENTO_1', 'João'
        );

        ProcessarMensagemWhatsapp::dispatchSync($tenant, $telefone, $mensagem->id);

        $requisicaoClaude = Http::recorded(fn ($request) => str_contains($request->url(), 'anthropic.com'))->first();
        $ferramentas = collect($requisicaoClaude[0]->data()['tools'])->pluck('name')->all();
        $this->assertNotContains('criar_agendamento', $ferramentas);
        $this->assertSame(1, Agendamento::where('tenant_id', $tenant->id)->count());
    }

    public function test_bot_nao_confirma_agendamento_se_a_ia_nao_persistiu_a_reserva(): void
    {
        $tenant = Tenant::create([
            'nome' => 'Barbearia Reserva Segura',
            'slug' => 'barbearia-reserva-segura',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'bot_ativo' => true,
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $telefone = '5551988000003';

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Show! Seu corte está agendado para amanhã às 10:30.']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);
        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $tenant, $telefone, '10:30', 'MSG_RESERVA_NAO_PERSISTIDA_1', 'João'
        );

        ProcessarMensagemWhatsapp::dispatchSync($tenant, $telefone, $mensagem->id);

        $this->assertDatabaseMissing('agendamentos', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('mensagens', [
            'remetente' => 'bot',
            'conteudo' => 'Não consegui concluir a reserva por aqui. Vou encaminhar você para confirmar o horário.',
        ]);
        $this->assertDatabaseHas('conversas', [
            'tenant_id' => $tenant->id,
            'telefone_cliente' => $telefone,
            'status_v2' => 'aguardando_humano',
        ]);
    }
}
