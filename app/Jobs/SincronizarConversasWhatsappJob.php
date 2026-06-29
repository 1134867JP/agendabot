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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SincronizarConversasWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 600; // 10 min — histórico de muitos chats demora

    // JIDs que não são conversas individuais reais
    private const JIDS_IGNORADOS = ['status@broadcast', 'broadcast'];

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(EvolutionApiService $evolution): void
    {
        if (!$this->tenant->evolution_instance) {
            Cache::forget("sync_whatsapp_tenant_{$this->tenant->id}");
            return;
        }

        $instance = $this->tenant->evolution_instance;

        // ── 1. Mapa nome→telefone via findContacts ───────────────────────────
        $nomesPorTelefone = $this->buildNomesMap($evolution->fetchContacts($instance));

        // ── 2. Buscar todos os chats ─────────────────────────────────────────
        $chats = $evolution->fetchChats($instance);
        $importados = 0;
        $sem_nome   = 0;

        foreach ($chats as $chat) {
            $remoteJid = data_get($chat, 'remoteJid') ?? data_get($chat, 'id');
            if (!$remoteJid) continue;

            // Ignorar grupos, broadcast e newsletters
            if ($this->deveIgnorar($remoteJid)) continue;

            $telefone = $this->limparJid($remoteJid);

            // ── Nome: findContacts > pushName do chat > lastMessage.pushName ──
            $nomeChat = $nomesPorTelefone[$telefone]
                ?? $nomesPorTelefone[$this->normalizar($telefone)]
                ?? (data_get($chat, 'pushName') ?: null)
                ?? (data_get($chat, 'lastMessage.pushName') ?: null)
                ?? null;

            // ── Cliente ──────────────────────────────────────────────────────
            $cliente = Cliente::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'telefone' => $telefone],
                ['nome' => $nomeChat ?? $telefone]
            );

            // ── Conversa ─────────────────────────────────────────────────────
            $conversa = Conversa::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'telefone_cliente' => $telefone],
                ['cliente_id' => $cliente->id, 'status_v2' => 'ativa']
            );

            // ── Mensagens ────────────────────────────────────────────────────
            $msgs = $evolution->fetchMessages($instance, $remoteJid, 100);

            // Extrair nome das mensagens como último fallback
            // pushName no nível da mensagem = nome do contato (mesmo em fromMe=true na Evolution API)
            if (!$nomeChat) {
                foreach ($msgs as $msg) {
                    $pn = data_get($msg, 'pushName');
                    if ($pn && $pn !== '') {
                        $nomeChat = $pn;
                        break;
                    }
                }
            }

            // Atualizar nome do cliente se ainda é placeholder
            $ehPlaceholder = $cliente->nome === $telefone
                || strtolower($cliente->nome) === 'cliente whatsapp';

            if ($nomeChat && ($ehPlaceholder || $nomeChat !== $cliente->nome)) {
                $cliente->update(['nome' => $nomeChat]);
            }

            if (!$nomeChat) $sem_nome++;

            // Importar mensagens ainda não gravadas
            foreach ($msgs as $msg) {
                $evolutionId = data_get($msg, 'key.id');
                if (!$evolutionId) continue;
                if (Mensagem::where('evolution_message_id', $evolutionId)->exists()) continue;

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

                if ($tipo === 'texto' && $conteudo === '') continue;

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

            // Atualizar ultima_mensagem_em
            $ultima = $conversa->mensagens()->orderByDesc('enviada_em')->value('enviada_em');
            if ($ultima) {
                $conversa->update(['ultima_mensagem_em' => $ultima]);
            }
        }

        Log::info('SINCRONIZAR_CONVERSAS', [
            'tenant'     => $this->tenant->slug,
            'chats'      => count($chats),
            'importados' => $importados,
            'sem_nome'   => $sem_nome,
            'nomes_map'  => count($nomesPorTelefone),
        ]);

        Cache::forget("sync_whatsapp_tenant_{$this->tenant->id}");
    }

    public function failed(\Throwable $e): void
    {
        Cache::forget("sync_whatsapp_tenant_{$this->tenant->id}");
        Log::error('SINCRONIZAR_CONVERSAS_FALHOU', [
            'tenant' => $this->tenant->slug,
            'erro'   => $e->getMessage(),
        ]);
    }

    /** Remove qualquer sufixo de JID (@s.whatsapp.net, @c.us, @lid, etc.) */
    private function limparJid(string $jid): string
    {
        return preg_replace('/@.*$/', '', $jid);
    }

    /**
     * Monta mapa telefone → nome a partir do retorno de findContacts.
     * Tenta vários formatos de campo para garantir compatibilidade.
     */
    private function buildNomesMap(array $contatos): array
    {
        $mapa = [];

        foreach ($contatos as $c) {
            // JID pode estar em remoteJid, id ou jid
            $jid  = data_get($c, 'remoteJid')
                 ?? data_get($c, 'id')
                 ?? data_get($c, 'jid');
            // Nome preferido: pushName > notify > name (agenda do celular)
            $nome = data_get($c, 'pushName')
                 ?? data_get($c, 'notify')
                 ?? data_get($c, 'name');

            if (!$jid || !$nome) continue;

            $tel = preg_replace('/@.*$/', '', $jid);
            $mapa[$tel] = $nome;
        }

        return $mapa;
    }

    private function deveIgnorar(string $remoteJid): bool
    {
        // Grupos WhatsApp
        if (str_contains($remoteJid, '@g.us'))      return true;
        // Newsletters
        if (str_contains($remoteJid, '@newsletter')) return true;
        // Broadcast
        if (in_array($remoteJid, self::JIDS_IGNORADOS)) return true;
        // Formato antigo de grupo (número-número)
        $tel = str_replace('@s.whatsapp.net', '', $remoteJid);
        if (str_contains($tel, '-')) return true;

        return false;
    }

    /**
     * Normaliza telefone BR: remove o 9 extra para tentar match alternativo.
     * Ex: 554899999999 → 54899999999 (sem o dígito extra)
     */
    private function normalizar(string $tel): string
    {
        // Se o número BR tem 13 dígitos (55 + DDD + 9 + 8 dígitos), tenta sem o 9
        if (strlen($tel) === 13 && str_starts_with($tel, '55')) {
            return '55' . substr($tel, 2, 2) . substr($tel, 5);
        }
        return $tel;
    }
}
