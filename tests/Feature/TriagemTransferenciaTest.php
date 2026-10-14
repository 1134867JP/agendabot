<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Services\ConversaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TriagemTransferenciaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'nome' => 'Clínica Triagem',
            'slug' => 'clinica-triagem',
            'tipo_servico' => 'clinica',
            'modo_bot' => 'triagem',
            'ativo' => true,
            'bot_ativo' => true,
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function test_recusa_transferencia_com_nome_generico_e_sem_preferencia(): void
    {
        // Visto em produção: o modelo transferia uma pergunta de preço inventando
        // nome ("Paciente") e preferência, sem coletar nada do paciente.
        $this->fakeIaTransferenciaSeguidaDe(
            ['resumo' => 'Dúvida sobre preço', 'nome_cliente' => 'Paciente', 'preferencia' => ''],
            'A avaliação é gratuita! Qual o seu nome e o melhor turno para você?',
        );

        $conversa = $this->pacienteEnvia('5551955000001', 'Quanto custa a avaliação?');

        $this->assertSame('ativa', $conversa->status_v2);
        $this->assertSame('A avaliação é gratuita! Qual o seu nome e o melhor turno para você?', $this->ultimaRespostaDoBot($conversa));
        $resultado = $this->resultadoDaFerramentaNaSegundaChamada();
        $this->assertFalse($resultado['sucesso']);
        $this->assertSame('dados_incompletos', $resultado['erro']);
        $this->assertStringContainsString('nome', $resultado['mensagem']);
        $this->assertStringContainsString('preferência', $resultado['mensagem']);
    }

    public function test_transfere_quando_tem_nome_e_preferencia(): void
    {
        $this->fakeIaTransferenciaSeguidaDe(
            ['resumo' => 'Avaliação', 'nome_cliente' => 'Ana Souza', 'preferencia' => 'quinta de manhã'],
            'não deve ser usada',
        );

        $conversa = $this->pacienteEnvia('5551955000002', 'Sou a Ana Souza, quinta de manhã');

        $this->assertSame('aguardando_humano', $conversa->status_v2);
    }

    public function test_transfere_sem_dados_quando_paciente_pede_atendente(): void
    {
        $this->fakeIaTransferenciaSeguidaDe(
            ['resumo' => 'Pediu atendente', 'nome_cliente' => 'Cliente', 'preferencia' => ''],
            'não deve ser usada',
        );

        $conversa = $this->pacienteEnvia('5551955000003', 'Quero falar com um atendente');

        $this->assertSame('aguardando_humano', $conversa->status_v2);
    }

    public function test_transfere_sem_dados_quando_a_conversa_nao_avanca(): void
    {
        $sync = app(ConversaSyncService::class);
        foreach (['oi', 'hã?', 'não entendi'] as $i => $texto) {
            $sync->registrarMensagemRecebida($this->tenant, '5551955000004', $texto, "MSG_TRIAGEM_TRAVADA_{$i}", 'Paciente');
        }
        $this->fakeIaTransferenciaSeguidaDe(
            ['resumo' => 'Paciente não conseguiu explicar', 'nome_cliente' => 'Paciente', 'preferencia' => ''],
            'não deve ser usada',
        );

        $conversa = $this->pacienteEnvia('5551955000004', 'ainda não entendi');

        $this->assertSame('aguardando_humano', $conversa->status_v2);
    }

    private function fakeIaTransferenciaSeguidaDe(array $input, string $textoFinal): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'transferir_para_humano', 'input' => $input]],
                    'stop_reason' => 'tool_use',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ])
                ->push([
                    'content' => [['type' => 'text', 'text' => $textoFinal]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ]),
            '*' => Http::response(['status' => 'success'], 200),
        ]);
    }

    private function pacienteEnvia(string $telefone, string $texto): Conversa
    {
        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $this->tenant, $telefone, $texto, 'MSG_'.$telefone.'_'.md5($texto), 'Paciente'
        );
        ProcessarMensagemWhatsapp::dispatchSync($this->tenant, $telefone, $mensagem->id);

        return Conversa::where('tenant_id', $this->tenant->id)->where('telefone_cliente', $telefone)->firstOrFail();
    }

    private function ultimaRespostaDoBot(Conversa $conversa): string
    {
        return (string) $conversa->mensagens()->where('remetente', 'bot')->latest('id')->value('conteudo');
    }

    private function resultadoDaFerramentaNaSegundaChamada(): array
    {
        $segunda = Http::recorded(fn ($request) => str_contains($request->url(), 'anthropic.com'))->values()[1][0]->data();
        $toolResult = collect(end($segunda['messages'])['content'])->firstWhere('type', 'tool_result');

        return json_decode($toolResult['content'], true);
    }
}
