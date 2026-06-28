<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SincronizarConversasWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(EvolutionApiService $evolution): void
    {
        if (!$this->tenant->evolution_instance) {
            return;
        }

        $chats = $evolution->fetchChats($this->tenant->evolution_instance);
        $importados = 0;

        foreach ($chats as $chat) {
            $remoteJid = data_get($chat, 'remoteJid');
            if (!$remoteJid || str_contains($remoteJid, '@g.us')) {
                continue;
            }

            $telefone = str_replace('@s.whatsapp.net', '', $remoteJid);
            if (str_contains($telefone, '-')) {
                continue; // formato antigo de grupo
            }

            $nome = data_get($chat, 'pushName') ?? $telefone;

            $cliente = Cliente::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'telefone' => $telefone],
                ['nome' => $nome]
            );

            // Atualizar nome se ainda está como telefone (placeholder do cadastro anterior)
            if ($cliente->nome === $telefone && $nome !== $telefone) {
                $cliente->update(['nome' => $nome]);
            }

            $conversa = Conversa::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'telefone_cliente' => $telefone],
                ['cliente_id' => $cliente->id, 'status_v2' => 'ativa']
            );

            $msgs = $evolution->fetchMessages($this->tenant->evolution_instance, $remoteJid, 100);
            foreach ($msgs as $msg) {
                $evolutionId = data_get($msg, 'key.id');
                if (!$evolutionId) {
                    continue;
                }

                if (Mensagem::where('evolution_message_id', $evolutionId)->exists()) {
                    continue;
                }

                $fromMe      = (bool) data_get($msg, 'key.fromMe', false);
                $messageType = data_get($msg, 'messageType', 'conversation');

                [$tipo, $conteudo] = match ($messageType) {
                    'imageMessage'    => ['imagem',    data_get($msg, 'message.imageMessage.caption', '')],
                    'videoMessage'    => ['video',     data_get($msg, 'message.videoMessage.caption', '')],
                    'audioMessage'    => ['audio',     ''],
                    'documentMessage' => ['documento', data_get($msg, 'message.documentMessage.fileName', '')],
                    'stickerMessage'  => ['sticker',   ''],
                    default           => ['texto',     data_get($msg, 'message.conversation')
                                                    ?? data_get($msg, 'message.extendedTextMessage.text')
                                                    ?? ''],
                };

                if ($tipo === 'texto' && $conteudo === '') {
                    continue; // ignorar mensagens sem conteúdo
                }

                $ts = data_get($msg, 'messageTimestamp');

                $conversa->mensagens()->create([
                    'remetente'            => $fromMe ? 'humano' : 'cliente',
                    'tipo'                 => $tipo,
                    'conteudo'             => $conteudo,
                    'evolution_message_id' => $evolutionId,
                    'enviada_em'           => $ts ? Carbon::createFromTimestamp((int) $ts) : now(),
                ]);

                $importados++;
            }

            $ultima = $conversa->mensagens()->orderByDesc('enviada_em')->value('enviada_em');
            if ($ultima) {
                $conversa->update(['ultima_mensagem_em' => $ultima]);
            }
        }

        Log::info('SINCRONIZAR_CONVERSAS', [
            'tenant'     => $this->tenant->slug,
            'chats'      => count($chats),
            'importados' => $importados,
        ]);
    }
}
