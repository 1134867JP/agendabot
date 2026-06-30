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
    public int $timeout = 600;

    private const JIDS_IGNORADOS = ['status@broadcast', 'broadcast'];

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(EvolutionApiService $evolution): void
    {
        if (!$this->tenant->evolution_instance) {
            Cache::forget("sync_whatsapp_tenant_{$this->tenant->id}");
            return;
        }

        $instance = $this->tenant->evolution_instance;

        Log::info('SYNC_START', ['tenant' => $this->tenant->slug]);

        // ── 1. Mapa nome→telefone via findContacts ───────────────────────────
        $nomesPorTelefone = $this->buildNomesMap($evolution->fetchContacts($instance));

        // ── 2. Todos os chats ────────────────────────────────────────────────
        $chats = $evolution->fetchChats($instance);

        $totalChats  = count($chats);
        $importados  = 0;
        $erros       = 0;
        $semMensagem = 0;

        foreach ($chats as $chat) {
            try {
                $result = $this->processarChat(
                    $evolution, $instance, $chat, $nomesPorTelefone
                );
                $importados  += $result['importados'];
                $semMensagem += $result['sem_mensagem'] ? 1 : 0;
            } catch (\Throwable $e) {
                $erros++;
                Log::warning('SYNC_CHAT_ERRO', [
                    'tenant' => $this->tenant->slug,
                    'jid'    => data_get($chat, 'remoteJid') ?? data_get($chat, 'id') ?? '?',
                    'erro'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('SYNC_DONE', [
            'tenant'        => $this->tenant->slug,
            'total_chats'   => $totalChats,
            'importados'    => $importados,
            'sem_mensagem'  => $semMensagem,
            'erros'         => $erros,
            'nomes_map'     => count($nomesPorTelefone),
        ]);

        Cache::forget("sync_whatsapp_tenant_{$this->tenant->id}");
    }

    private function processarChat(
        EvolutionApiService $evolution,
        string $instance,
        array $chat,
        array $nomesPorTelefone,
    ): array {
        $remoteJid = data_get($chat, 'remoteJid') ?? data_get($chat, 'id');
        if (!$remoteJid) return ['importados' => 0, 'sem_mensagem' => true];

        if ($this->deveIgnorar($remoteJid)) return ['importados' => 0, 'sem_mensagem' => true];

        $isLid    = str_contains($remoteJid, '@lid');
        $telefone = $this->limparJid($remoteJid);

        // ── Nome: findContacts > pushName do chat > lastMessage.pushName ──────
        $nomeChat = $nomesPorTelefone[$telefone]
            ?? $nomesPorTelefone[$this->normalizar($telefone)]
            ?? (data_get($chat, 'pushName') ?: null)
            ?? (data_get($chat, 'lastMessage.pushName') ?: null)
            ?? null;

        // ── Cliente e Conversa ────────────────────────────────────────────────
        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone' => $telefone],
            ['nome' => $nomeChat ?? $telefone]
        );

        $conversa = Conversa::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone_cliente' => $telefone],
            ['cliente_id' => $cliente->id, 'status_v2' => 'ativa']
        );

        // ── Mensagens ─────────────────────────────────────────────────────────
        // Busca com o JID original; se @lid sem resultado, tenta @s.whatsapp.net
        $msgs = $this->fetchMsgsComFallback($evolution, $instance, $remoteJid, $isLid, $telefone);

        // Extrair nome das mensagens como último fallback
        if (!$nomeChat) {
            foreach ($msgs as $msg) {
                $pn = data_get($msg, 'pushName');
                if ($pn && $pn !== '') { $nomeChat = $pn; break; }
            }
        }

        // Atualiza nome se ainda é placeholder
        $ehPlaceholder = $cliente->nome === $telefone || strtolower($cliente->nome) === 'cliente whatsapp';
        if ($nomeChat && ($ehPlaceholder || $nomeChat !== $cliente->nome)) {
            $cliente->update(['nome' => $nomeChat]);
            $conversa->cliente()->update(['nome' => $nomeChat]);
        }

        // Se sem mensagens, tenta usar lastMessage do chat como fallback rápido
        if (empty($msgs)) {
            $lastMsg = data_get($chat, 'lastMessage');
            if ($lastMsg) $msgs = [$lastMsg];
        }

        $importados  = 0;
        $semMensagem = empty($msgs);

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
                default           => ['texto',
                    data_get($msg, 'message.conversation')
                    ?? data_get($msg, 'message.extendedTextMessage.text')
                    ?? data_get($msg, 'message.ephemeralMessage.message.extendedTextMessage.text')
                    ?? '',
                ],
            };

            // Pula texto vazio (mas não mídias)
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

        // Atualiza ultima_mensagem_em a partir do banco (mais preciso)
        $ultima = $conversa->mensagens()->orderByDesc('enviada_em')->value('enviada_em');
        if (!$ultima) {
            // Fallback: pega o timestamp do lastMessage do chat
            $chatTs = data_get($chat, 'lastMessage.messageTimestamp')
                   ?? data_get($chat, 'updatedAt');
            if ($chatTs) {
                $ultima = is_numeric($chatTs)
                    ? Carbon::createFromTimestamp((int) $chatTs)
                    : Carbon::parse($chatTs);
            }
        }
        if ($ultima) $conversa->update(['ultima_mensagem_em' => $ultima]);

        return ['importados' => $importados, 'sem_mensagem' => $semMensagem];
    }

    /**
     * Busca mensagens com fallback para @lid:
     * tenta o JID original; se @lid e vazio, tenta @s.whatsapp.net.
     */
    private function fetchMsgsComFallback(
        EvolutionApiService $evolution,
        string $instance,
        string $remoteJid,
        bool $isLid,
        string $telefone,
    ): array {
        $msgs = $evolution->fetchMessages($instance, $remoteJid, 50);

        if (empty($msgs) && $isLid) {
            // @lid não encontrou — tenta com o número real (@s.whatsapp.net)
            $jidAlternativo = $telefone . '@s.whatsapp.net';
            $msgs = $evolution->fetchMessages($instance, $jidAlternativo, 50);

            if (!empty($msgs)) {
                Log::info('SYNC_LID_FALLBACK', [
                    'tenant' => $this->tenant->slug,
                    'lid'    => $remoteJid,
                    'found'  => count($msgs),
                ]);
            }
        }

        return $msgs;
    }

    public function failed(\Throwable $e): void
    {
        Cache::forget("sync_whatsapp_tenant_{$this->tenant->id}");
        Log::error('SYNC_FALHOU', [
            'tenant' => $this->tenant->slug,
            'erro'   => $e->getMessage(),
        ]);
    }

    private function limparJid(string $jid): string
    {
        return preg_replace('/@.*$/', '', $jid);
    }

    private function buildNomesMap(array $contatos): array
    {
        $mapa = [];
        foreach ($contatos as $c) {
            $jid  = data_get($c, 'remoteJid') ?? data_get($c, 'id') ?? data_get($c, 'jid');
            $nome = data_get($c, 'pushName') ?? data_get($c, 'notify') ?? data_get($c, 'name');
            if (!$jid || !$nome) continue;
            $tel = preg_replace('/@.*$/', '', $jid);
            $mapa[$tel] = $nome;
        }
        return $mapa;
    }

    private function deveIgnorar(string $remoteJid): bool
    {
        if (str_contains($remoteJid, '@g.us'))      return true;
        if (str_contains($remoteJid, '@newsletter')) return true;
        if (in_array($remoteJid, self::JIDS_IGNORADOS)) return true;
        $tel = preg_replace('/@.*$/', '', $remoteJid);
        if (str_contains($tel, '-')) return true;
        return false;
    }

    private function normalizar(string $tel): string
    {
        if (strlen($tel) === 13 && str_starts_with($tel, '55')) {
            return '55' . substr($tel, 2, 2) . substr($tel, 5);
        }
        return $tel;
    }
}
