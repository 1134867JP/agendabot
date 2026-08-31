<?php

namespace Tests\Unit;

use App\Services\AI\DTO\AiRequest;
use App\Services\AI\Exceptions\AiProviderException;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\GroqProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProvidersTest extends TestCase
{
    public function test_gemini_permite_fallback_quando_modelo_nao_existe(): void
    {
        config([
            'ai.providers.gemini.key' => 'gemini-test',
            'ai.providers.gemini.base_url' => 'https://gemini.test/v1beta',
            'ai.fallback_statuses' => [404],
        ]);
        Http::fake([
            'gemini.test/*' => Http::response([
                'error' => [
                    'message' => 'Model not found.',
                    'status' => 'NOT_FOUND',
                ],
            ], 404),
        ]);

        try {
            (new GeminiProvider)->chat(new AiRequest([
                'messages' => [['role' => 'user', 'content' => 'Olá']],
            ], 'gemini-indisponivel'));
            $this->fail('Era esperada uma falha do provider.');
        } catch (AiProviderException $e) {
            $this->assertSame(404, $e->status);
            $this->assertSame('NOT_FOUND', $e->errorType);
            $this->assertTrue($e->fallbackAllowed);
        }
    }

    public function test_gemini_permite_fallback_em_erro_400_de_argumento_invalido(): void
    {
        // Um 400 é específico da serialização/estado de UM provider (ex.: histórico de
        // function-calling multi-turno incompatível só para o Gemini) — não deve impedir
        // que o orquestrador tente os demais providers, que têm payload e auth próprios.
        config([
            'ai.providers.gemini.key' => 'gemini-test',
            'ai.providers.gemini.base_url' => 'https://gemini.test/v1beta',
            'ai.fallback_statuses' => [400, 404, 408, 409, 425, 429, 500, 502, 503, 504, 529],
        ]);
        Http::fake([
            'gemini.test/*' => Http::response([
                'error' => [
                    'message' => 'Invalid JSON payload received. Unable to submit request because it must have a Content property.',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
        ]);

        try {
            (new GeminiProvider)->chat(new AiRequest([
                'messages' => [['role' => 'user', 'content' => 'Olá']],
            ], 'gemini-3.6-flash'));
            $this->fail('Era esperada uma falha do provider.');
        } catch (AiProviderException $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame('INVALID_ARGUMENT', $e->errorType);
            $this->assertTrue($e->fallbackAllowed);
        }
    }

    public function test_gemini_trata_function_call_sem_nome_como_falha_recuperavel(): void
    {
        config([
            'ai.providers.gemini.key' => 'gemini-test',
            'ai.providers.gemini.base_url' => 'https://gemini.test/v1beta',
        ]);
        Http::fake([
            'gemini.test/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['functionCall' => ['args' => []]]]],
                ]],
            ]),
        ]);

        try {
            (new GeminiProvider)->chat(new AiRequest([
                'messages' => [['role' => 'user', 'content' => 'Olá']],
            ], 'gemini-3.6-flash'));
            $this->fail('Era esperada uma falha do provider.');
        } catch (AiProviderException $e) {
            $this->assertSame('invalid_tool_call', $e->errorType);
            $this->assertTrue($e->fallbackAllowed);
        }
    }

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

    public function test_gemini_normaliza_schema_de_ferramenta_com_tipo_uniao_e_sem_propriedades(): void
    {
        config([
            'ai.providers.gemini.key' => 'gemini-test',
            'ai.providers.gemini.base_url' => 'https://gemini.test/v1beta',
        ]);
        Http::fake([
            'gemini.test/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Ok!']]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 2],
            ]),
        ]);

        (new GeminiProvider)->chat(new AiRequest([
            'messages' => [['role' => 'user', 'content' => 'Quero agendar']],
            'tools' => [
                [
                    'name' => 'criar_agendamento',
                    'description' => 'Cria agendamento',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'cliente_nome' => ['type' => 'string', 'description' => 'Nome'],
                            'observacoes' => ['type' => ['string', 'null'], 'description' => 'Opcional'],
                        ],
                        'required' => ['cliente_nome'],
                    ],
                ],
                [
                    'name' => 'confirmar_agendamento',
                    'description' => 'Confirma',
                    'input_schema' => ['type' => 'object', 'properties' => new \stdClass],
                ],
            ],
        ], 'gemini-3.6-flash'));

        $declarations = Http::recorded()[0][0]->data()['tools'][0]['functionDeclarations'];

        // Tipo-união ['string','null'] vira type=string + nullable=true (OpenAPI 3.0).
        $observacoes = $declarations[0]['parameters']['properties']['observacoes'];
        $this->assertSame('string', $observacoes['type']);
        $this->assertTrue($observacoes['nullable']);
        $this->assertSame(['cliente_nome'], $declarations[0]['parameters']['required']);

        // Ferramenta sem propriedades não deve enviar "parameters" (Gemini rejeita objeto vazio).
        $this->assertSame('confirmar_agendamento', $declarations[1]['name']);
        $this->assertArrayNotHasKey('parameters', $declarations[1]);
    }

    public function test_gemini_serializa_args_vazio_da_ferramenta_como_objeto(): void
    {
        config([
            'ai.providers.gemini.key' => 'gemini-test',
            'ai.providers.gemini.base_url' => 'https://gemini.test/v1beta',
        ]);
        Http::fake([
            'gemini.test/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Confirmado.']]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 2],
            ]),
        ]);

        (new GeminiProvider)->chat(new AiRequest([
            'messages' => [
                ['role' => 'user', 'content' => 'Pode confirmar?'],
                ['role' => 'assistant', 'content' => [[
                    'type' => 'tool_use',
                    'id' => 'fc_1',
                    'name' => 'confirmar_agendamento',
                    'input' => [],
                ]]],
            ],
        ], 'gemini-3.6-flash'));

        $body = json_decode(Http::recorded()[0][0]->body());

        $this->assertInstanceOf(\stdClass::class, $body->contents[1]->parts[0]->functionCall->args);
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

        $first = $provider->chat(new AiRequest($basePayload, 'gemini-3.6-flash'));
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
        ]), 'gemini-3.6-flash'));

        $requests = Http::recorded();
        $secondPayload = $requests[1][0]->data();
        $this->assertSame('assinatura', $secondPayload['contents'][1]['parts'][0]['thoughtSignature']);
        $this->assertSame('fc_1', $secondPayload['contents'][2]['parts'][0]['functionResponse']['id']);
        $this->assertSame('buscar_slots', $secondPayload['contents'][2]['parts'][0]['functionResponse']['name']);
    }
}
