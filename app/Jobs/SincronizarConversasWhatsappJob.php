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
    public int $timeout = 300;

    // Nomes que indicam placeholder — serão substituídos pelo nome real
    private const PLACEHOLDERS = ['Cliente WhatsApp', 'cliente whatsapp'];

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

            // pushName do fetchChats é null para contatos individuais nesta versão da API
            // O nome real vem das mensagens (campo pushName em cada msg do cliente)
            $nomeChat = data_get($chat, 'pushName');

            $cliente = Cliente::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'telefone' => $telefone],
                ['nome' => $nomeChat ?? $telefone]
            );

            $conversa = Conversa::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'telefone_cliente' => $telefone],
                ['cliente_id' => $cliente->id, 'status_v2' => 'ativa']
            );

            $msgs = $evolution->fetchMessages($this->tenant->evolution_instance, $remoteJid, 100);

            // Extrair nome real do cliente a partir das mensagens recebidas (fromMe=false)
            $nomeReal = $nomeChat;
            if (!$nomeReal) {
                foreach ($msgs as $msg) {
                    if (!data_get($msg, 'key.fromMe') && data_get($msg, 'pushName')) {
                        $nomeReal = data_get($msg, 'pushName');
                        break;
                    }
                }
            }

            // Atualizar nome se ainda é um placeholder (telefone ou "Cliente WhatsApp")
            if ($nomeReal && $nomeReal !== $telefone) {
                $nomePlaceholder = $cliente->nome === $telefone
                    || in_array(strtolower($cliente->nome), array_map('strtolower', self::PLACEHOLDERS));

                if ($nomePlaceholder) {
                    $cliente->update(['nome' => $nomeReal]);
                }
            }

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
                    continue;
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
