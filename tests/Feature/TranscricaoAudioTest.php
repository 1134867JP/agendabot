<?php

namespace Tests\Feature;

use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Services\TranscricaoAudioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranscricaoAudioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Conversa $conversa;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.evolution.url' => 'http://evolution.test',
            'services.evolution.key' => 'evo-key',
            'services.transcricao.ativa' => true,
            'services.transcricao.key' => 'groq-key',
            'services.transcricao.base_url' => 'https://groq.test/openai/v1',
        ]);

        $this->tenant = Tenant::create([
            'nome' => 'Barbearia Áudio',
            'slug' => 'barbearia-audio',
            'tipo_servico' => 'barbeiro',
            'ativo' => true,
            'evolution_instance' => 'barbearia-audio',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $this->conversa = Conversa::create([
            'tenant_id' => $this->tenant->id,
            'telefone_cliente' => '5551999999999',
            'status_v2' => 'ativa',
        ]);
    }

    public function test_audio_do_cliente_vira_texto(): void
    {
        Http::fake([
            'evolution.test/chat/getBase64FromMediaMessage/*' => Http::response(['base64' => base64_encode('ogg-bytes')]),
            'groq.test/*' => Http::response(['text' => ' Quero cortar o cabelo amanhã de tarde ']),
        ]);
        $mensagem = $this->audio('AUDIO-1');

        app(TranscricaoAudioService::class)->transcreverPendentes($this->tenant, $this->conversa->id, '5551999999999');

        $this->assertSame('[áudio transcrito] Quero cortar o cabelo amanhã de tarde', $mensagem->fresh()->conteudo);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'audio/transcriptions')
            && $request->hasHeader('Authorization', 'Bearer groq-key'));
    }

    public function test_falha_na_transcricao_mantem_marcador_para_o_bot_pedir_texto(): void
    {
        Http::fake([
            'evolution.test/*' => Http::response(['base64' => base64_encode('ogg-bytes')]),
            'groq.test/*' => Http::response(['error' => 'rate limit'], 429),
        ]);
        $mensagem = $this->audio('AUDIO-2');

        app(TranscricaoAudioService::class)->transcreverPendentes($this->tenant, $this->conversa->id, '5551999999999');

        $this->assertSame('[áudio]', $mensagem->fresh()->conteudo);
    }

    public function test_sem_chave_nao_chama_servicos_externos(): void
    {
        config(['services.transcricao.key' => null]);
        Http::fake();
        $this->audio('AUDIO-3');

        app(TranscricaoAudioService::class)->transcreverPendentes($this->tenant, $this->conversa->id, '5551999999999');

        Http::assertNothingSent();
    }

    private function audio(string $id): Mensagem
    {
        return $this->conversa->registrarMensagem('cliente', '[áudio]', $id, 'audio');
    }
}
