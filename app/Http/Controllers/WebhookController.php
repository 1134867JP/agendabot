<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarMensagemJob;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function handle(Request $request, string $tenantSlug): Response
    {
        $tenant = Tenant::where('slug', $tenantSlug)
            ->where('ativo', true)
            ->firstOrFail();

        $data = $request->json()->all();

        $tipo = data_get($data, 'data.messageType');
        if ($tipo !== 'conversation' && $tipo !== 'extendedTextMessage') {
            return response('ok');
        }

        if (data_get($data, 'data.key.fromMe')) {
            return response('ok');
        }

        $telefone = data_get($data, 'data.key.remoteJid');
        $mensagem = data_get($data, 'data.message.conversation')
                 ?? data_get($data, 'data.message.extendedTextMessage.text');

        if (! $telefone || ! $mensagem) {
            return response('ok');
        }

        ProcessarMensagemJob::dispatch($tenant, $telefone, $mensagem);

        return response('ok');
    }
}
