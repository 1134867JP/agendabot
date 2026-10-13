<?php

namespace App\Services;

use App\Models\Mensagem;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Converte em texto os áudios que o cliente manda pelo WhatsApp, para o bot
 * conseguir responder. Em qualquer falha a mensagem continua como "[áudio]"
 * e o bot pede para o cliente escrever.
 */
class TranscricaoAudioService
{
    public const MARCADOR_PENDENTE = '[áudio]';

    public const PREFIXO_TRANSCRITO = '[áudio transcrito]';

    public function __construct(private EvolutionApiService $evolution) {}

    public function ativa(): bool
    {
        return (bool) config('services.transcricao.ativa') && filled(config('services.transcricao.key'));
    }

    /**
     * Transcreve os áudios do cliente ainda não transcritos na conversa (o
     * debounce pode ter agrupado mais de um áudio na mesma resposta).
     */
    public function transcreverPendentes(Tenant $tenant, int $conversaId, string $telefone, int $limite = 3): void
    {
        if (! $this->ativa() || ! $tenant->evolution_instance) {
            return;
        }

        Mensagem::where('conversa_id', $conversaId)
            ->where('remetente', 'cliente')
            ->where('tipo', 'audio')
            ->where('conteudo', self::MARCADOR_PENDENTE)
            ->whereNotNull('evolution_message_id')
            ->where('created_at', '>=', now()->subHour())
            ->latest('id')
            ->limit($limite)
            ->get()
            ->each(function (Mensagem $mensagem) use ($tenant, $telefone): void {
                $texto = $this->transcrever($tenant, $mensagem, $telefone);
                if ($texto !== null) {
                    $mensagem->update(['conteudo' => self::PREFIXO_TRANSCRITO.' '.$texto]);
                }
            });
    }

    private function transcrever(Tenant $tenant, Mensagem $mensagem, string $telefone): ?string
    {
        try {
            $midia = $this->evolution->fetchMedia(
                $tenant->evolution_instance,
                $mensagem->evolution_message_id,
                false,
                preg_replace('/\D/', '', $telefone).'@s.whatsapp.net',
            );

            $audio = base64_decode((string) data_get($midia, 'base64', ''), true);
            if (! $audio || strlen($audio) > (int) config('services.transcricao.max_bytes')) {
                return null;
            }

            $response = Http::withToken(config('services.transcricao.key'))
                ->connectTimeout(5)
                ->timeout(30)
                ->attach('file', $audio, 'audio.ogg')
                ->post(rtrim(config('services.transcricao.base_url'), '/').'/audio/transcriptions', [
                    'model' => config('services.transcricao.model'),
                    'language' => 'pt',
                    'response_format' => 'json',
                ]);

            $texto = trim((string) $response->json('text'));
            if (! $response->successful() || $texto === '') {
                Log::warning('AUDIO_TRANSCRICAO_FALHOU', ['tenant_id' => $tenant->id, 'status' => $response->status()]);

                return null;
            }

            return mb_substr($texto, 0, 2000);
        } catch (Throwable $e) {
            Log::warning('AUDIO_TRANSCRICAO_ERRO', ['tenant_id' => $tenant->id, 'erro' => $e->getMessage()]);

            return null;
        }
    }
}
