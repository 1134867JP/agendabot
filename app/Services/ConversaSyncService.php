<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Centraliza a lógica de upsert de Cliente/Conversa a partir de dados da Evolution API,
 * reutilizada tanto pela sincronização retroativa (SincronizarConversasWhatsappJob)
 * quanto pelos eventos de webhook em tempo real (chats.upsert/contacts.upsert).
 */
class ConversaSyncService
{
    private const JIDS_IGNORADOS  = ['status@broadcast', 'broadcast'];
    private const NOMES_INVALIDOS = ['você', 'you', 'cliente whatsapp', ''];

    /**
     * Persiste imediatamente uma mensagem recebida via webhook (messages.upsert):
     * garante Cliente e Conversa (upsert atômico) e salva a Mensagem do cliente com
     * dedup por evolution_message_id. Retorna null quando a mensagem é duplicata.
     *
     * Persistir no webhook (e não no job) permite que jobs atrasados por debounce
     * detectem mensagens mais novas do mesmo cliente antes de responder.
     */
    public function registrarMensagemRecebida(
        Tenant $tenant,
        string $telefone,
        string $conteudo,
        ?string $evolutionMessageId = null,
        ?string $pushName = null,
        string $tipo = 'texto',
    ): ?Mensagem {
        // Dedup rápido por evolution_message_id (a constraint unique cobre a corrida)
        if ($evolutionMessageId && Mensagem::where('evolution_message_id', $evolutionMessageId)->exists()) {
            return null;
        }

        [, $conversa] = $this->upsertClienteEConversa(
            $tenant,
            $telefone,
            $this->nomeValido($pushName),
        );

        try {
            return DB::transaction(fn () => $conversa->registrarMensagem('cliente', $conteudo, $evolutionMessageId, $tipo));
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
            Log::info('MENSAGEM_DUPLICADA_IGNORADA', [
                'tenant' => $tenant->id,
                'evolution_message_id' => $evolutionMessageId,
            ]);

            return null;
        }
    }

    /**
     * firstOrCreate não é atômico: sob concorrência (ex. sync retroativo e mensagem em
     * tempo real chegando juntos), duas execuções podem tentar inserir o mesmo registro
     * único ao mesmo tempo. Se isso acontecer, a segunda apenas busca o registro que a
     * primeira já criou, em vez de propagar a exceção de unique constraint.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    public function firstOrCreateSafe(string $modelClass, array $unique, array $extra = []): Model
    {
        try {
            // DB::transaction usa SAVEPOINT quando já há uma transação em andamento, então uma
            // violação de unique constraint aqui não aborta uma transação externa mais ampla.
            return DB::transaction(fn () => $modelClass::firstOrCreate($unique, $extra));
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            return $modelClass::where($unique)->firstOrFail();
        }
    }

    public function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505' || str_contains($e->getMessage(), 'duplicate key value violates unique constraint');
    }

    /**
     * Processa um chat completo (nome + histórico de mensagens). Usado na sincronização
     * retroativa, onde o custo de buscar mensagens por chat é aceitável.
     */
    public function processarChat(
        Tenant $tenant,
        EvolutionApiService $evolution,
        string $instance,
        array $chat,
        array $nomesPorTelefone,
    ): array {
        $remoteJid = data_get($chat, 'remoteJid') ?? data_get($chat, 'id');
        if (! $remoteJid || $this->deveIgnorar($remoteJid)) {
            return ['importados' => 0, 'sem_mensagem' => true, 'ignorado' => true];
        }

        $isLid = str_contains($remoteJid, '@lid');
        $telefone = $this->resolverTelefoneDoChat($chat, $remoteJid);

        // Primeiro confirma o histórico. Não cria Cliente/Conversa para chats vazios.
        $msgs = $this->fetchMsgsComFallback(
            $tenant,
            $evolution,
            $instance,
            $remoteJid,
            $isLid,
            $telefone,
        );

        if (! $telefone) {
            $telefone = $this->resolverTelefoneDasMensagens($msgs);
        }

        // Um @lid sem número real não deve virar um contato numérico falso no sistema.
        if (! $telefone || ! $this->telefoneValido($telefone)) {
            return ['importados' => 0, 'sem_mensagem' => true, 'ignorado' => true];
        }

        if (empty($msgs) && data_get($chat, 'lastMessage')) {
            $msgs = [data_get($chat, 'lastMessage')];
        }

        $mensagensImportaveis = collect($msgs)
            ->map(fn (array $msg) => $this->normalizarMensagem($msg))
            ->filter()
            ->values();

        if ($mensagensImportaveis->isEmpty()) {
            return ['importados' => 0, 'sem_mensagem' => true, 'ignorado' => false];
        }

        $lastMsgFromMe = (bool) data_get($chat, 'lastMessage.key.fromMe', false);
        $nomeChat = $this->buscarNomeNoMapa($nomesPorTelefone, $telefone)
            ?? $this->extrairNome($chat)
            ?? ($lastMsgFromMe ? null : $this->extrairNome((array) data_get($chat, 'lastMessage', [])));

        if (! $nomeChat) {
            foreach ($msgs as $msg) {
                if (data_get($msg, 'key.fromMe')) {
                    continue;
                }

                $nomeChat = $this->extrairNome($msg);
                if ($nomeChat) {
                    break;
                }
            }
        }

        [$cliente, $conversa] = $this->upsertClienteEConversa($tenant, $telefone, $nomeChat);
        $importados = 0;

        foreach ($mensagensImportaveis as $msg) {
            if (Mensagem::where('evolution_message_id', $msg['evolution_id'])->exists()) {
                continue;
            }

            try {
                $conversa->mensagens()->create([
                    'remetente'            => $msg['from_me'] ? 'humano' : 'cliente',
                    'tipo'                 => $msg['tipo'],
                    'conteudo'             => $msg['conteudo'],
                    'evolution_message_id' => $msg['evolution_id'],
                    'enviada_em'           => $msg['timestamp']
                        ? Carbon::createFromTimestamp($msg['timestamp'])
                        : now(),
                ]);
                $importados++;
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }
            }
        }

        $ultima = $conversa->mensagens()->orderByDesc('enviada_em')->value('enviada_em');
        if ($ultima) {
            $conversa->update(['ultima_mensagem_em' => $ultima]);
        }

        return ['importados' => $importados, 'sem_mensagem' => false, 'ignorado' => false];
    }

    /**
     * Upsert leve de Cliente/Conversa a partir de um evento chats.upsert: garante que a
     * conversa exista e atualiza o nome se possível, SEM buscar o histórico de mensagens
     * (evita golpear a Evolution API a cada evento recebido em tempo real).
     */
    public function upsertChatLeve(Tenant $tenant, array $chat): ?Conversa
    {
        $remoteJid = data_get($chat, 'remoteJid') ?? data_get($chat, 'id');
        if (! $remoteJid || $this->deveIgnorar($remoteJid)) {
            return null;
        }

        $telefone = $this->resolverTelefoneDoChat($chat, $remoteJid);
        if (! $telefone) {
            return null;
        }

        $lastMsgFromMe = (bool) data_get($chat, 'lastMessage.key.fromMe', false);
        $nomeChat = $this->extrairNome($chat)
            ?? ($lastMsgFromMe ? null : $this->extrairNome((array) data_get($chat, 'lastMessage', [])));

        [, $conversa] = $this->upsertClienteEConversa($tenant, $telefone, $nomeChat);

        return $conversa;
    }

    /**
     * Atualiza o nome do cliente a partir de um evento contacts.upsert, apenas se o nome
     * atual ainda for um placeholder (nunca sobrescreve um nome já válido).
     */
    public function processarContatoLeve(Tenant $tenant, array $contato): ?Cliente
    {
        $jid = data_get($contato, 'remoteJid') ?? data_get($contato, 'id') ?? data_get($contato, 'jid');
        if (! $jid || $this->deveIgnorar($jid)) {
            return null;
        }

        $telefone = $this->primeiroTelefoneValido([
            data_get($contato, 'remoteJidAlt'),
            data_get($contato, 'phoneNumber'),
            $jid,
        ]);
        $nome = $this->extrairNome($contato);

        if (! $telefone || ! $nome) {
            return null;
        }

        $cliente = $this->encontrarMelhorCliente($tenant, $telefone)
            ?? $this->firstOrCreateSafe(
                Cliente::class,
                ['tenant_id' => $tenant->id, 'telefone' => $telefone],
                ['nome' => $nome],
            );

        if ($this->nomeEhPlaceholder($cliente->nome) && $nome !== $cliente->nome) {
            $cliente->update(['nome' => $nome]);
        }

        $this->reconciliarConversasDoCliente($tenant, $telefone, $cliente);

        return $cliente;
    }

    /**
     * Ordena os chats do mais recente para o mais antigo (por última mensagem) e limita
     * a quantidade — evita sincronizar centenas de conversas inativas de uma vez, priorizando
     * as mais ativas. Conversas fora do corte continuam chegando normalmente em tempo real
     * via webhook (chats.upsert/messages.upsert) na próxima interação do cliente.
     */
    public function chatsRecentesLimitados(array $chats, int $limite): array
    {
        return collect($chats)
            ->sortByDesc(fn (array $chat) => $this->timestampOrdenacao($chat))
            ->take($limite)
            ->values()
            ->all();
    }

    private function timestampOrdenacao(array $chat): int
    {
        $ts = data_get($chat, 'lastMessage.messageTimestamp') ?? data_get($chat, 'updatedAt');

        if (is_numeric($ts)) {
            return (int) $ts;
        }

        if (is_string($ts) && $ts !== '') {
            try {
                return Carbon::parse($ts)->timestamp;
            } catch (\Throwable $e) {
                return 0;
            }
        }

        return 0;
    }

    public function buildNomesMap(array $contatos): array
    {
        $mapa = [];

        foreach ($contatos as $contato) {
            $nome = $this->extrairNome($contato);
            $telefone = $this->primeiroTelefoneValido([
                data_get($contato, 'remoteJidAlt'),
                data_get($contato, 'phoneNumber'),
                data_get($contato, 'remoteJid'),
                data_get($contato, 'id'),
                data_get($contato, 'jid'),
            ]);

            if (! $telefone || ! $nome) {
                continue;
            }

            foreach ($this->variantesTelefone($telefone) as $variante) {
                $mapa[$variante] = $nome;
            }
        }

        return $mapa;
    }

    public function resolverTelefoneMensagem(array $mensagem): ?string
    {
        return $this->primeiroTelefoneValido([
            data_get($mensagem, 'key.remoteJidAlt'),
            data_get($mensagem, 'key.participantAlt'),
            data_get($mensagem, 'key.remoteJid'),
        ]);
    }

    public function limparRegistrosVazios(Tenant $tenant): array
    {
        return DB::transaction(function () use ($tenant): array {
            $conversas = Conversa::where('tenant_id', $tenant->id)
                ->where('status_v2', 'ativa')
                ->whereNull('ultima_mensagem_em')
                ->doesntHave('mensagens')
                ->get();

            $conversasRemovidas = $conversas->count();
            foreach ($conversas as $conversa) {
                $conversa->delete();
            }

            $clientesRemovidos = Cliente::where('tenant_id', $tenant->id)
                ->doesntHave('conversas')
                ->doesntHave('agendamentos')
                ->where(function ($query) {
                    $query->whereColumn('nome', 'telefone')
                        ->orWhereRaw('LOWER(TRIM(nome)) IN (?, ?)', ['cliente whatsapp', '']);
                })
                ->delete();

            return [
                'conversas' => $conversasRemovidas,
                'clientes' => $clientesRemovidos,
            ];
        });
    }

    private function resolverTelefoneDoChat(array $chat, string $remoteJid): ?string
    {
        $candidatos = [
            str_contains($remoteJid, '@lid') ? null : $remoteJid,
            data_get($chat, 'remoteJidAlt'),
            data_get($chat, 'lastMessage.key.remoteJidAlt'),
            data_get($chat, 'lastMessage.key.participantAlt'),
            data_get($chat, 'phoneNumber'),
        ];

        return $this->primeiroTelefoneValido($candidatos);
    }

    private function resolverTelefoneDasMensagens(array $mensagens): ?string
    {
        foreach ($mensagens as $mensagem) {
            $telefone = $this->primeiroTelefoneValido([
                data_get($mensagem, 'key.remoteJidAlt'),
                data_get($mensagem, 'key.participantAlt'),
                str_contains((string) data_get($mensagem, 'key.remoteJid'), '@lid')
                    ? null
                    : data_get($mensagem, 'key.remoteJid'),
            ]);

            if ($telefone) {
                return $telefone;
            }
        }

        return null;
    }

    private function primeiroTelefoneValido(array $candidatos): ?string
    {
        foreach ($candidatos as $candidato) {
            if (
                ! is_string($candidato)
                || $candidato === ''
                || str_contains($candidato, '@lid')
                || str_contains($candidato, '@g.us')
                || str_contains($candidato, '@newsletter')
                || str_contains($candidato, 'broadcast')
            ) {
                continue;
            }

            $telefone = preg_replace('/\D/', '', preg_replace('/@.*$/', '', $candidato));
            if ($this->telefoneValido($telefone)) {
                return $telefone;
            }
        }

        return null;
    }

    private function telefoneValido(?string $telefone): bool
    {
        return is_string($telefone) && preg_match('/^\d{10,15}$/', $telefone) === 1;
    }

    private function normalizarMensagem(array $msg): ?array
    {
        $evolutionId = data_get($msg, 'key.id');
        if (! $evolutionId) {
            return null;
        }

        $messageType = data_get($msg, 'messageType', 'conversation');
        [$tipo, $conteudo] = match ($messageType) {
            'imageMessage' => ['imagem', data_get($msg, 'message.imageMessage.caption', '')],
            'videoMessage' => ['video', data_get($msg, 'message.videoMessage.caption', '')],
            'audioMessage' => ['audio', ''],
            'documentMessage' => ['documento', data_get($msg, 'message.documentMessage.fileName', '')],
            'stickerMessage' => ['sticker', ''],
            default => [
                'texto',
                data_get($msg, 'message.conversation')
                    ?? data_get($msg, 'message.extendedTextMessage.text')
                    ?? data_get($msg, 'message.ephemeralMessage.message.extendedTextMessage.text')
                    ?? '',
            ],
        };

        if ($tipo === 'texto' && trim((string) $conteudo) === '') {
            return null;
        }

        $timestamp = data_get($msg, 'messageTimestamp');

        return [
            'evolution_id' => (string) $evolutionId,
            'from_me' => (bool) data_get($msg, 'key.fromMe', false),
            'tipo' => $tipo,
            'conteudo' => (string) $conteudo,
            'timestamp' => is_numeric($timestamp) ? (int) $timestamp : null,
        ];
    }

    /**
     * Busca mensagens com fallback para @lid: tenta o JID original; se @lid e vazio,
     * tenta @s.whatsapp.net.
     */
    private function fetchMsgsComFallback(
        Tenant $tenant,
        EvolutionApiService $evolution,
        string $instance,
        string $remoteJid,
        bool $isLid,
        ?string $telefone,
    ): array {
        $msgs = $evolution->fetchMessages($instance, $remoteJid, 50);

        if (empty($msgs) && $isLid && $telefone) {
            $jidAlternativo = $telefone.'@s.whatsapp.net';
            $msgs = $evolution->fetchMessages($instance, $jidAlternativo, 50);

            if (! empty($msgs)) {
                Log::info('SYNC_LID_FALLBACK', [
                    'tenant' => $tenant->slug,
                    'lid' => $remoteJid,
                    'found' => count($msgs),
                ]);
            }
        }

        return $msgs;
    }

    /**
     * @return array{0: Cliente, 1: Conversa}
     */
    private function upsertClienteEConversa(Tenant $tenant, string $telefone, ?string $nomeChat): array
    {
        $variantes = $this->variantesTelefone($telefone);
        $conversas = Conversa::where('tenant_id', $tenant->id)
            ->whereIn('telefone_cliente', $variantes)
            ->with('cliente')
            ->get();
        $conversa = $conversas->firstWhere('telefone_cliente', $telefone) ?? $conversas->first();

        $cliente = $conversa?->cliente && ! $this->nomeEhPlaceholder($conversa->cliente->nome)
            ? $conversa->cliente
            : $this->encontrarMelhorCliente($tenant, $telefone);

        $cliente ??= $this->firstOrCreateSafe(
            Cliente::class,
            ['tenant_id' => $tenant->id, 'telefone' => $telefone],
            ['nome' => $nomeChat ?? $telefone],
        );

        $conversa ??= $this->firstOrCreateSafe(
            Conversa::class,
            ['tenant_id' => $tenant->id, 'telefone_cliente' => $telefone],
            ['cliente_id' => $cliente->id, 'status_v2' => 'ativa'],
        );

        $clienteAtual = $conversa->cliente;
        if (! $conversa->cliente_id || ($clienteAtual && $this->nomeEhPlaceholder($clienteAtual->nome))) {
            $conversa->update(['cliente_id' => $cliente->id]);
            $conversa->setRelation('cliente', $cliente);
        }

        $this->atualizarNomeSePlaceholder($cliente, $conversa, $nomeChat);

        return [$cliente, $conversa];
    }

    private function atualizarNomeSePlaceholder(Cliente $cliente, Conversa $conversa, ?string $nomeChat): void
    {
        if ($nomeChat && $this->nomeEhPlaceholder($cliente->nome)) {
            $cliente->update(['nome' => $nomeChat]);
        }
    }

    private function encontrarMelhorCliente(Tenant $tenant, string $telefone): ?Cliente
    {
        $clientes = Cliente::where('tenant_id', $tenant->id)
            ->whereIn('telefone', $this->variantesTelefone($telefone))
            ->get();

        $exato = $clientes->firstWhere('telefone', $telefone);
        if ($exato && ! $this->nomeEhPlaceholder($exato->nome)) {
            return $exato;
        }

        return $clientes->first(fn (Cliente $cliente) => ! $this->nomeEhPlaceholder($cliente->nome))
            ?? $exato
            ?? $clientes->first();
    }

    private function reconciliarConversasDoCliente(Tenant $tenant, string $telefone, Cliente $cliente): void
    {
        $conversas = Conversa::where('tenant_id', $tenant->id)
            ->whereIn('telefone_cliente', $this->variantesTelefone($telefone))
            ->with('cliente')
            ->get();

        foreach ($conversas as $conversa) {
            if (! $conversa->cliente_id || $this->nomeEhPlaceholder($conversa->cliente?->nome)) {
                $conversa->update(['cliente_id' => $cliente->id]);
            }
        }
    }

    private function buscarNomeNoMapa(array $nomesPorTelefone, string $telefone): ?string
    {
        foreach ($this->variantesTelefone($telefone) as $variante) {
            $nome = $this->nomeValido($nomesPorTelefone[$variante] ?? null);
            if ($nome) {
                return $nome;
            }
        }

        return null;
    }

    private function extrairNome(array $fonte): ?string
    {
        $campos = [
            'name', 'contactName', 'savedName', 'formattedName', 'fullName', 'shortName',
            'pushName', 'notify', 'verifiedName', 'businessName', 'senderName',
            'contact.name', 'contact.pushName',
        ];

        foreach ($campos as $campo) {
            $valor = data_get($fonte, $campo);
            $nome = is_string($valor) ? $this->nomeValido($valor) : null;

            if ($nome) {
                return $nome;
            }
        }

        return null;
    }

    private function nomeEhPlaceholder(?string $nome): bool
    {
        if (! $nome) {
            return true;
        }

        $limpo = trim($nome);

        return preg_match('/^\+?[\d\s().-]+$/', $limpo) === 1
            || in_array(strtolower($limpo), self::NOMES_INVALIDOS, true);
    }

    /**
     * Retorna o nome se for utilizável (não vazio e não um placeholder como "Você"/"You").
     */
    private function nomeValido(?string $nome): ?string
    {
        if ($this->nomeEhPlaceholder($nome)) {
            return null;
        }

        return trim($nome);
    }

    private function limparJid(string $jid): string
    {
        return preg_replace('/@.*$/', '', $jid);
    }

    private function deveIgnorar(string $remoteJid): bool
    {
        if (str_contains($remoteJid, '@g.us')) {
            return true;
        }
        if (str_contains($remoteJid, '@newsletter')) {
            return true;
        }
        if (in_array($remoteJid, self::JIDS_IGNORADOS)) {
            return true;
        }
        $tel = preg_replace('/@.*$/', '', $remoteJid);
        return str_contains($tel, '-');
    }

    private function variantesTelefone(string $telefone): array
    {
        $telefone = preg_replace('/\D/', '', $telefone);
        $variantes = [$telefone];

        if (str_starts_with($telefone, '55') && strlen($telefone) === 13 && $telefone[4] === '9') {
            $variantes[] = substr($telefone, 0, 4).substr($telefone, 5);
        } elseif (str_starts_with($telefone, '55') && strlen($telefone) === 12) {
            $variantes[] = substr($telefone, 0, 4).'9'.substr($telefone, 4);
        } elseif (strlen($telefone) === 11) {
            $variantes[] = '55'.$telefone;
            if ($telefone[2] === '9') {
                $variantes[] = '55'.substr($telefone, 0, 2).substr($telefone, 3);
            }
        } elseif (strlen($telefone) === 10) {
            $variantes[] = '55'.$telefone;
            $variantes[] = '55'.substr($telefone, 0, 2).'9'.substr($telefone, 2);
        }

        return array_values(array_unique(array_filter($variantes)));
    }
}
