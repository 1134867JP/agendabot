<?php

namespace Tests\Unit;

use App\Services\AI\DTO\AiRequest;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProvidersTest extends TestCase
{
    public function test_groq_normaliza_tool_call_e_tokens_em_cache(): void
    {
        config([
            'ai.providers.groq.key' => 'groq-test',
            'ai.providers.groq.base_url' => 'https://api.groq.test/openai/v1',
        ]);
        Http::fake([
            'api.groq.test/*' => Http::response([
                'choices' => [[
                    'finish_reason' => 'tool_calls',
                    'message' => [
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'buscar_slots', 'arguments' => '{"dias":3}'],
                        ]],
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 20,
                    'prompt_tokens_details' => ['cached_tokens' => 40],
                ],
            ]),
        ]);

        $response = (new GroqProvider)->chat(new AiRequest([
            'system' => [['type' => 'text', 'text' => 'Sistema']],
            'messages' => [['role' => 'user', 'content' => 'Quero agendar']],
            'tools' => [[
                'name' => 'buscar_slots',
                'description' => 'Busca horários',
                'input_schema' => ['type' => 'object', 'properties' => ['dias' => ['type' => 'integer']]],
            ]],
        ], 'openai/gpt-oss-120b'));

        $this->assertSame('tool_use', $response->stopReason);
        $this->assertSame('buscar_slots', $response->content[0]['name']);
        $this->assertSame(['dias' => 3], $response->content[0]['input']);
        $this->assertSame(80, $response->usage['input_tokens']);
        $this->assertSame(40, $response->usage['cache_read_input_tokens']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.groq.test/openai/v1/chat/completions'
            && $request['messages'][0] === ['role' => 'system', 'content' => 'Sistema']
            && $request['tools'][0]['function']['name'] === 'buscar_slots');
    }

    public function test_gemini_preserva_id_e_thought_signature_no_retorno_da_ferramenta(): void
    {
        config([
            'ai.providers.gemini.key' => 'gemini-test',
            'ai.providers.gemini.base_url' => 'https://gemini.test/v1beta',
        ]);
        Http::fake([
            'gemini.test/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'functionCall' => ['id' => 'fc_1', 'name' => 'buscar_slots', 'args' => ['dias' => 2]],
                            'thoughtSignature' => 'assinatura',
                        ]]],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 4],
                ])
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => 'Encontrei dois horários.']]],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => ['promptTokenCount' => 20, 'candidatesTokenCount' => 5],
                ]),
        ]);

        $provider = new GeminiProvider;
        $basePayload = [
            'messages' => [['role' => 'user', 'content' => 'Tem horário?']],
            'tools' => [[
                'name' => 'buscar_slots',
                'description' => 'Busca horários',
                'input_schema' => ['type' => 'object', 'properties' => []],
            ]],
        ];

        $first = $provider->chat(new AiRequest($basePayload, 'gemini-3.5-flash'));
        $this->assertSame('assinatura', $first->content[0]['thought_signature']);

        $provider->chat(new AiRequest(array_merge($basePayload, [
            'messages' => [
                ...$basePayload['messages'],
                ['role' => 'assistant', 'content' => $first->content],
                ['role' => 'user', 'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => 'fc_1',
                    'content' => '{"slots":["09:00"]}',
                ]]],
            ],
        ]), 'gemini-3.5-flash'));

        $requests = Http::recorded();
        $secondPayload = $requests[1][0]->data();
        $this->assertSame('assinatura', $secondPayload['contents'][1]['parts'][0]['thoughtSignature']);
        $this->assertSame('fc_1', $secondPayload['contents'][2]['parts'][0]['functionResponse']['id']);
        $this->assertSame('buscar_slots', $secondPayload['contents'][2]['parts'][0]['functionResponse']['name']);
    }
}
