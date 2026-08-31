<?php

namespace Tests\Feature;

use App\Jobs\ProcessarMensagemWhatsapp;
use App\Models\Conversa;
use App\Models\Tenant;
use App\Services\ConversaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversaIaFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversa_responde_quando_gemini_rejeita_a_requisicao_e_o_fallback_esta_disponivel(): void
    {
        config([
            'ai.providers.gemini.key' => 'gemini-test',
            'ai.providers.gemini.base_url' => 'https://gemini.test/v1beta',
            'ai.providers.groq.key' => 'groq-test',
            'ai.providers.groq.base_url' => 'https://groq.test/openai/v1',
        ]);

        Http::fake([
            'gemini.test/*' => Http::response([
                'error' => [
                    'message' => 'Invalid JSON payload received.',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
            'groq.test/*' => Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => 'Olá! Como posso ajudar você?'],
                ]],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8],
            ]),
            '*' => Http::response(['status' => 'success']),
        ]);

        $tenant = Tenant::create([
            'nome' => 'Barbearia Fallback',
            'slug' => 'barbearia-fallback',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'bot_ativo' => true,
            'evolution_instance' => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'configuracoes' => [
                'ai' => [
                    'provider' => 'gemini',
                    'fallback_providers' => ['groq'],
                    'models' => ['gemini' => 'gemini-2.5-flash'],
                ],
            ],
        ]);

        $telefone = '5551966000010';
        $mensagem = app(ConversaSyncService::class)->registrarMensagemRecebida(
            $tenant, $telefone, 'Oi, quero agendar um corte', 'FALLBACK_IA_1', 'Cliente Teste',
        );

        ProcessarMensagemWhatsapp::dispatchSync($tenant, $telefone, $mensagem->id);

        $conversa = Conversa::where('tenant_id', $tenant->id)
            ->where('telefone_cliente', $telefone)
            ->firstOrFail();

        $this->assertDatabaseHas('mensagens', [
            'conversa_id' => $conversa->id,
            'remetente' => 'bot',
            'conteudo' => 'Olá! Como posso ajudar você?',
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-3.6-flash'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'groq.test/openai/v1/chat/completions'));
    }
}
