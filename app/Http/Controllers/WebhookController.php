<?php

namespace App\Http\Controllers;

use App\Jobs\BackupELimparHistoricoJob;
use App\Jobs\ProcessarMensagemWhatsapp;
use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request, string $tenantSlug): Response
    {
        $tenant = Tenant::where('slug', $tenantSlug)
            ->where('ativo', true)
            ->firstOrFail();

        $data = $request->json()->all();

        // Log para debug do formato Evolution v2
        Log::info('WEBHOOK_RAW', ['tenant' => $tenantSlug, 'event' => data_get($data, 'event'), 'keys' => array_keys($data)]);

        // Atualizar status de conexão quando WhatsApp conecta/desconecta
        $event = data_get($data, 'event');
        if ($event === 'connection.update' || $event === 'CONNECTION_UPDATE') {
            $state     = data_get($data, 'data.state') ?? data_get($data, 'data.instance.state');
            $conectado = $state === 'open';
            if ($tenant->whatsapp_conectado !== $conectado) {
                $tenant->update(['whatsapp_conectado' => $conectado]);
                Log::info('WHATSAPP_CONNECTION_UPDATE', ['tenant' => $tenantSlug, 'state' => $state, 'conectado' => $conectado]);

                if ($conectado) {
                    // Ao conectar: importar histórico em background
                    SincronizarConversasWhatsappJob::dispatch($tenant)->onQueue('default');
                } else {
                    // Ao desconectar: fazer backup e limpar histórico do banco
                    BackupELimparHistoricoJob::dispatch($tenant)->onQueue('default');
                }
            }
            return response('ok');
        }

        if (! $tenant->bot_ativo) {
            return response('ok');
        }

        // Evolution API v2: MESSAGES_UPSERT pode vir como objeto ou array em data
        $msgData = data_get($data, 'data');
        if (is_array($msgData) && isset($msgData[0])) {
            $msgData = $msgData[0]; // v2 às vezes envolve em array
        }

        if (data_get($msgData, 'key.fromMe')) {
            return response('ok');
        }

        $remoteJid = data_get($msgData, 'key.remoteJid') ?? '';

        // Ignorar grupos: formato novo (@g.us) e formato antigo ({phone}-{timestamp}@s.whatsapp.net)
        if (str_contains($remoteJid, '@g.us')) {
            return response('ok');
        }

        // Remover sufixo @s.whatsapp.net (JID format da Evolution API)
        $telefone = str_replace('@s.whatsapp.net', '', $remoteJid);

        // Grupos no formato antigo ficam como "555491234567-1580740050" (com hífen)
        if (str_contains($telefone, '-')) {
            return response('ok');
        }

        if (! $telefone) {
            return response('ok');
        }

        $messageType = data_get($msgData, 'messageType');

        // Mapear tipo Evolution → tipo interno
        $tipoMidia = match ($messageType) {
            'imageMessage'    => 'imagem',
            'audioMessage'    => 'audio',
            'videoMessage'    => 'video',
            'documentMessage' => 'documento',
            'stickerMessage'  => 'sticker',
            default           => null,
        };

        // Conteúdo: texto real para textos, texto sintético para o Claude reagir a mídias
        $conteudoClaude = match (true) {
            $messageType === 'conversation'        => data_get($msgData, 'message.conversation'),
            $messageType === 'extendedTextMessage' => data_get($msgData, 'message.extendedTextMessage.text'),
            $tipoMidia === 'imagem'   => data_get($msgData, 'message.imageMessage.caption') ?: '[imagem]',
            $tipoMidia === 'audio'    => '[áudio]',
            $tipoMidia === 'video'    => data_get($msgData, 'message.videoMessage.caption') ?: '[vídeo]',
            $tipoMidia === 'documento'=> data_get($msgData, 'message.documentMessage.fileName') ?: '[documento]',
            $tipoMidia === 'sticker'  => '[figurinha]',
            default                   => null,
        };

        if (! $conteudoClaude) {
            Log::info('WEBHOOK_SKIP', ['tipo' => $messageType]);
            return response('ok');
        }

        $evolutionMessageId = data_get($msgData, 'key.id');
        $pushName           = data_get($msgData, 'pushName') ?: null;
        $tipoInterno        = $tipoMidia ?? 'texto';

        Log::info('WEBHOOK_MSG', ['telefone' => $telefone, 'tipo' => $messageType, 'push_name' => $pushName, 'mensagem' => mb_substr($conteudoClaude, 0, 200)]);

        ProcessarMensagemWhatsapp::dispatch($tenant, $telefone, $conteudoClaude, $evolutionMessageId, $pushName, $tipoInterno);

        return response('ok');
    }
}
