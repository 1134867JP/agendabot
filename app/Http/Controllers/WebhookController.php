<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarMensagemJob;
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

        if (! $tenant->bot_ativo) {
            return response('ok');
        }

        // Evolution API v2: MESSAGES_UPSERT pode vir como objeto ou array em data
        $msgData = data_get($data, 'data');
        if (is_array($msgData) && isset($msgData[0])) {
            $msgData = $msgData[0]; // v2 às vezes envolve em array
        }

        $tipo = data_get($msgData, 'messageType');
        if ($tipo !== 'conversation' && $tipo !== 'extendedTextMessage') {
            Log::info('WEBHOOK_SKIP', ['tipo' => $tipo]);
            return response('ok');
        }

        if (data_get($msgData, 'key.fromMe')) {
            return response('ok');
        }

        // Remover sufixo @s.whatsapp.net (JID format da Evolution API)
        $telefone = str_replace(['@s.whatsapp.net', '@g.us'], '', data_get($msgData, 'key.remoteJid') ?? '');
        $mensagem = data_get($msgData, 'message.conversation')
                 ?? data_get($msgData, 'message.extendedTextMessage.text');

        Log::info('WEBHOOK_MSG', ['telefone' => $telefone, 'mensagem' => $mensagem]);

        if (! $telefone || ! $mensagem) {
            return response('ok');
        }

        $evolutionMessageId = data_get($msgData, 'key.id');
        ProcessarMensagemWhatsapp::dispatch($tenant, $telefone, $mensagem, $evolutionMessageId);

        return response('ok');
    }
}
