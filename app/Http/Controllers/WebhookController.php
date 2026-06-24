<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarMensagemWhatsapp;
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
            $state = data_get($data, 'data.state') ?? data_get($data, 'data.instance.state');
            $conectado = $state === 'open';
            if ($tenant->whatsapp_conectado !== $conectado) {
                $tenant->update(['whatsapp_conectado' => $conectado]);
                Log::info('WHATSAPP_CONNECTION_UPDATE', ['tenant' => $tenantSlug, 'state' => $state, 'conectado' => $conectado]);
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

        // Remover sufixo @s.whatsapp.net (JID format da Evolution API)
        $telefone = str_replace(['@s.whatsapp.net', '@g.us'], '', data_get($msgData, 'key.remoteJid') ?? '');

        if (! $telefone) {
            return response('ok');
        }

        $tipo = data_get($msgData, 'messageType');

        // Tipos de mídia: converter em texto sintético para o Claude reagir
        $midiaSintetica = match ($tipo) {
            'stickerMessage'  => '[figurinha]',
            'imageMessage'    => '[imagem]',
            'audioMessage'    => '[áudio]',
            'videoMessage'    => '[vídeo]',
            'documentMessage' => '[documento]',
            default           => null,
        };

        $mensagem = match (true) {
            $tipo === 'conversation'         => data_get($msgData, 'message.conversation'),
            $tipo === 'extendedTextMessage'  => data_get($msgData, 'message.extendedTextMessage.text'),
            $midiaSintetica !== null         => $midiaSintetica,
            default                          => null,
        };

        if (! $mensagem) {
            Log::info('WEBHOOK_SKIP', ['tipo' => $tipo]);
            return response('ok');
        }

        Log::info('WEBHOOK_MSG', ['telefone' => $telefone, 'tipo' => $tipo, 'mensagem' => $mensagem]);

        $evolutionMessageId = data_get($msgData, 'key.id');
        ProcessarMensagemWhatsapp::dispatch($tenant, $telefone, $mensagem, $evolutionMessageId);

        return response('ok');
    }
}
