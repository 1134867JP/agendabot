<?php

namespace App\Http\Controllers;

use App\Jobs\BackupELimparHistoricoJob;
use App\Jobs\ProcessarMensagemWhatsapp;
use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Tenant;
use App\Services\ConversaSyncService;
use App\Services\WhatsAppSyncState;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(
        Request $request,
        string $tenantSlug,
        ConversaSyncService $sync,
        WhatsAppSyncState $syncState,
    ): Response
    {
        $tenant = Tenant::where('slug', $tenantSlug)
            ->where('ativo', true)
            ->firstOrFail();

        $providedToken = (string) $request->header('X-Webhook-Token', '');
        if ($providedToken === '' && str_starts_with($request->header('Authorization', ''), 'Bearer ')) {
            $providedToken = substr($request->header('Authorization'), 7);
        }

        if (! $tenant->webhook_token || $providedToken === '' || ! hash_equals($tenant->webhook_token, $providedToken)) {
            Log::warning('WEBHOOK_UNAUTHORIZED', ['tenant_id' => $tenant->id]);
            return response('Unauthorized', 401);
        }

        $data = $request->json()->all();

        // Log para debug do formato Evolution v2
        Log::info('WEBHOOK_RECEIVED', ['tenant_id' => $tenant->id, 'event' => data_get($data, 'event')]);

        // Atualizar status de conexão quando WhatsApp conecta/desconecta
        $event = data_get($data, 'event');
        if ($event === 'connection.update' || $event === 'CONNECTION_UPDATE') {
            $state = data_get($data, 'data.state') ?? data_get($data, 'data.instance.state');
            $conectado = $state === 'open';
            if ($tenant->whatsapp_conectado !== $conectado) {
                $tenant->update(['whatsapp_conectado' => $conectado]);
                Log::info('WHATSAPP_CONNECTION_UPDATE', ['tenant' => $tenantSlug, 'state' => $state, 'conectado' => $conectado]);

                if ($conectado) {
                    // Ao conectar: importar histórico em background
                    $executionId = $syncState->iniciar($tenant);
                    SincronizarConversasWhatsappJob::dispatch($tenant, $executionId)->onQueue('sync');
                } else {
                    // Ao desconectar: fazer backup e limpar histórico do banco
                    BackupELimparHistoricoJob::dispatch($tenant)->onQueue('maintenance');
                }
            }

            return response('ok');
        }

        // Chats/contatos novos ou atualizados fora do fluxo de mensagem (nome, criação de conversa)
        if ($event === 'chats.upsert' || $event === 'CHATS_UPSERT') {
            foreach ($this->normalizarLista(data_get($data, 'data')) as $chat) {
                $sync->upsertChatLeve($tenant, $chat);
            }

            return response('ok');
        }

        if ($event === 'contacts.upsert' || $event === 'CONTACTS_UPSERT') {
            foreach ($this->normalizarLista(data_get($data, 'data')) as $contato) {
                $sync->processarContatoLeve($tenant, $contato);
            }

            return response('ok');
        }

        if (! in_array($event, ['messages.upsert', 'MESSAGES_UPSERT'], true)) {
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

        // WhatsApp pode entregar identificadores @lid. Usa o número alternativo real
        // quando disponível e ignora grupos, newsletters e identificadores sem telefone.
        $telefone = $sync->resolverTelefoneMensagem($msgData);
        if (! $telefone) {
            return response('ok');
        }

        $messageType = data_get($msgData, 'messageType');

        // Mapear tipo Evolution → tipo interno
        $tipoMidia = match ($messageType) {
            'imageMessage' => 'imagem',
            'audioMessage' => 'audio',
            'videoMessage' => 'video',
            'documentMessage' => 'documento',
            'stickerMessage' => 'sticker',
            default => null,
        };

        // Conteúdo: texto real para textos, texto sintético para o Claude reagir a mídias
        $conteudoClaude = match (true) {
            $messageType === 'conversation' => data_get($msgData, 'message.conversation'),
            $messageType === 'extendedTextMessage' => data_get($msgData, 'message.extendedTextMessage.text'),
            $tipoMidia === 'imagem' => data_get($msgData, 'message.imageMessage.caption') ?: '[imagem]',
            $tipoMidia === 'audio' => '[áudio]',
            $tipoMidia === 'video' => data_get($msgData, 'message.videoMessage.caption') ?: '[vídeo]',
            $tipoMidia === 'documento' => data_get($msgData, 'message.documentMessage.fileName') ?: '[documento]',
            $tipoMidia === 'sticker' => '[figurinha]',
            default => null,
        };

        if (! $conteudoClaude) {
            Log::info('WEBHOOK_SKIP', ['tipo' => $messageType]);

            return response('ok');
        }

        $evolutionMessageId = data_get($msgData, 'key.id');
        $pushName = data_get($msgData, 'pushName') ?: null;
        $tipoInterno = $tipoMidia ?? 'texto';

        Log::info('WEBHOOK_MESSAGE_ACCEPTED', ['tenant_id' => $tenant->id, 'message_id' => $evolutionMessageId, 'tipo' => $messageType, 'conteudo_length' => mb_strlen($conteudoClaude)]);

        // Persistir a mensagem imediatamente (Cliente/Conversa/Mensagem com dedup por
        // evolution_message_id). Assim o job consegue detectar, na hora de processar,
        // se chegou mensagem mais nova do mesmo cliente (debounce).
        $mensagem = $sync->registrarMensagemRecebida($tenant, $telefone, $conteudoClaude, $evolutionMessageId, $pushName, $tipoInterno);

        if (! $mensagem) {
            // Duplicata (mesmo evolution_message_id) — nada a processar
            return response('ok');
        }

        // Bot desativado impede apenas a resposta automática. A mensagem precisa
        // continuar salva para aparecer no painel e poder ser assumida pela equipe.
        if (! $tenant->bot_ativo) {
            return response('ok');
        }

        $dispatch = ProcessarMensagemWhatsapp::dispatch($tenant, $telefone, $mensagem->id)
            ->onQueue('messages');

        $debounce = (int) config('bot.debounce_seconds', 5);
        if ($debounce > 0) {
            $dispatch->delay(now()->addSeconds($debounce));
        }

        return response('ok');
    }

    /**
     * Evolution API às vezes envia um único objeto em "data", às vezes uma lista.
     * Normaliza para sempre iterar uma lista de arrays.
     */
    private function normalizarLista(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        return array_is_list($data) ? array_filter($data, 'is_array') : [$data];
    }
}
