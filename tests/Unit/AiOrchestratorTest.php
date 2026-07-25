<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\TokenUsage;
use App\Services\AI\AiOrchestrator;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTO\AiRequest;
use App\Services\AI\DTO\AiResponse;
use App\Services\AI\Exceptions\AiLimitExceededException;
use App\Services\AI\Exceptions\AiProviderException;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_seleciona_modelo_do_tenant_e_faz_fallback_em_erro_transitorio(): void
    {
        config([
            'ai.providers.claude.model' => 'claude-default',
            'ai.providers.groq.model' => 'groq-default',
            'ai.pricing.groq.groq-tenant' => [
                'input' => 1,
                'output' => 2,
                'cache_write' => 0,
                'cache_read' => 0,
            ],
        ]);

        $tenant = $this->tenant([
            'provider' => 'claude',
            'fallback_providers' => ['groq'],
            'models' => ['groq' => 'groq-tenant'],
        ]);

        $claude = new FakeAiProvider('claude', function () {
            throw new AiProviderException('rate limit', 'claude', 429, 'rate_limit', true);
        });
        $groq = new FakeAiProvider('groq', fn (AiRequest $request) => new AiResponse(
            'groq',
            $request->model,
            [['type' => 'text', 'text' => 'ok']],
            'stop',
            [
                'input_tokens' => 1000,
                'output_tokens' => 500,
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => 0,
            ],
            0,
            12,
        ));

        $response = (new AiOrchestrator([$claude, $groq]))->processar($tenant, ['messages' => []]);

        $this->assertSame('groq', $response->provider);
        $this->assertSame('groq-tenant', $response->model);
        $this->assertSame(1, $claude->calls);
        $this->assertSame(1, $groq->calls);
        $this->assertDatabaseHas('token_usages', [
            'tenant_id' => $tenant->id,
            'provider' => 'groq',
            'model' => 'groq-tenant',
            'input_tokens' => 1000,
            'output_tokens' => 500,
        ]);
        $this->assertEqualsWithDelta(0.002, (float) TokenUsage::first()->cost_usd, 0.00000001);
    }

    public function test_nao_faz_fallback_em_erro_de_autenticacao(): void
    {
        $tenant = $this->tenant([
            'provider' => 'claude',
            'fallback_providers' => ['groq'],
        ]);

        $claude = new FakeAiProvider('claude', function () {
            throw new AiProviderException('unauthorized', 'claude', 401, 'authentication_error', false);
        });
        $groq = new FakeAiProvider('groq', fn () => throw new \RuntimeException('não deveria chamar'));

        $this->expectException(AiProviderException::class);

        try {
            (new AiOrchestrator([$claude, $groq]))->processar($tenant, ['messages' => []]);
        } finally {
            $this->assertSame(0, $groq->calls);
        }
    }

    public function test_bloqueia_chamada_ao_atingir_limite_mensal_do_tenant(): void
    {
        $tenant = $this->tenant([
            'provider' => 'claude',
            'monthly_token_limit' => 100,
        ]);
        TokenUsage::create([
            'tenant_id' => $tenant->id,
            'provider' => 'claude',
            'model' => 'test',
            'input_tokens' => 80,
            'output_tokens' => 20,
        ]);

        $provider = new FakeAiProvider('claude', fn () => throw new \RuntimeException('não deveria chamar'));

        $this->expectException(AiLimitExceededException::class);

        try {
            (new AiOrchestrator([$provider]))->processar($tenant, ['messages' => []]);
        } finally {
            $this->assertSame(0, $provider->calls);
        }
    }

    private function tenant(array $ai): Tenant
    {
        return Tenant::create([
            'nome' => 'Tenant IA',
            'slug' => 'tenant-ia-'.fake()->unique()->numerify('####'),
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'configuracoes' => ['ai' => $ai],
        ]);
    }
}

class FakeAiProvider implements AiProviderInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly string $providerName,
        private readonly Closure $callback,
    ) {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function configured(): bool
    {
        return true;
    }

    public function chat(AiRequest $request): AiResponse
    {
        $this->calls++;

        return ($this->callback)($request);
    }
}
