<?php

namespace App\Services;

use App\Exceptions\EvolutionApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class EvolutionApiService
{
    private string $baseUrl;

    private string $globalApiKey;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.evolution.url');
        $this->globalApiKey = (string) config('services.evolution.key');
    }

    private function http(int $timeout = 15, bool $retry = true): PendingRequest
    {
        $request = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->timeout($timeout)
            ->connectTimeout(5);

        return $retry ? $request->retry(3, 500, throw: false) : $request;
    }

    public function configurado(): bool
    {
        return filter_var($this->baseUrl, FILTER_VALIDATE_URL) !== false
            && trim($this->globalApiKey) !== '';
    }

    public function enviarMensagem(string $instance, string $telefone, string $mensagem): bool
    {
        // Não repetir POST automaticamente: em um timeout ambíguo a Evolution pode
        // ter aceitado a mensagem. A caixa de saída controla as novas tentativas.
        $response = $this->http(10, retry: false)
            ->post("{$this->baseUrl}/message/sendText/{$instance}", [
                'number' => $telefone,
                'text' => $mensagem,
            ]);

        return $response->successful();
    }

    public function criarInstancia(string $instanceName): array
    {
        $response = $this->http()
            ->post("{$this->baseUrl}/instance/create", [
                'instanceName' => $instanceName,
                'integration' => 'WHATSAPP-BAILEYS',
                'qrcode' => true,
            ]);

        if (! $response->successful() && ! $this->instanciaJaExiste($response->status(), $response->json())) {
            throw EvolutionApiException::requisicaoFalhou('criar instância', $response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * A Evolution pode devolver 403 ou 409 quando a instância já existe. Isso
     * não impede a conexão: basta solicitar um novo QR para a mesma instância.
     */
    private function instanciaJaExiste(int $status, mixed $body): bool
    {
        if (! in_array($status, [403, 409], true)) {
            return false;
        }

        $message = strtolower((string) (data_get($body, 'message') ?? data_get($body, 'error')));

        return str_contains($message, 'already exist')
            || str_contains($message, 'already registered')
            || str_contains($message, 'já existe');
    }

    public function obterQrCode(string $instance): ?string
    {
        // Logo após criar uma instância, a Evolution pode responder 200 antes de
        // terminar de gerar o QR. Fazemos poucas tentativas seguras (GET) para
        // não devolver uma tela vazia ao usuário nesse intervalo.
        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            $response = $this->http()
                ->get("{$this->baseUrl}/instance/connect/{$instance}");

            if (! $response->successful()) {
                throw EvolutionApiException::requisicaoFalhou('gerar QR Code', $response->status());
            }

            $body = $response->json();
            $qrcode = data_get($body, 'base64')
                ?? data_get($body, 'qrcode.base64')
                ?? data_get($body, 'qrcode');

            if (is_string($qrcode) && $qrcode !== '') {
                return $qrcode;
            }

            if ($tentativa < 2) {
                usleep(350_000);
            }
        }

        return null;
    }

    public function statusInstancia(string $instance): string
    {
        return $this->listarStatusInstancias()[$instance] ?? 'desconhecido';
        // open = conectado | close = desconectado | connecting = aguardando
    }

    /**
     * Consulta todas as instâncias de uma vez para o watchdog não fazer uma
     * requisição por tenant.
     *
     * @return array<string, string>
     */
    public function listarStatusInstancias(): array
    {
        $response = $this->http()
            ->get("{$this->baseUrl}/instance/fetchInstances");

        if (! $response->successful()) {
            throw EvolutionApiException::requisicaoFalhou('consultar instâncias', $response->status());
        }

        $instancias = $this->extrairRegistros($response->json(), ['instances']);

        return collect($instancias)
            ->filter(fn ($item) => is_array($item)
                && is_string($item['name'] ?? null)
                && is_string($item['connectionStatus'] ?? null))
            ->mapWithKeys(fn (array $item) => [$item['name'] => $item['connectionStatus']])
            ->all();
    }

    public function fetchChats(string $instance): array
    {
        $response = $this->http()
            ->post("{$this->baseUrl}/chat/findChats/{$instance}", []);

        return $this->extrairRegistros($response->json(), ['chats']);
    }

    public function fetchContacts(string $instance): array
    {
        $response = $this->http()
            ->post("{$this->baseUrl}/chat/findContacts/{$instance}", [
                'where' => (object) [],
            ]);

        return $this->extrairRegistros($response->json(), ['contacts']);
    }

    public function fetchMessages(string $instance, string $remoteJid, int $count = 50): array
    {
        $response = $this->http(20)
            ->post("{$this->baseUrl}/chat/findMessages/{$instance}", [
                'where' => ['key' => ['remoteJid' => $remoteJid]],
                'limit' => $count,
            ]);

        if (! $response->successful()) {
            \Log::debug('FETCH_MESSAGES_HTTP_ERR', [
                'instance' => $instance,
                'jid' => $remoteJid,
                'status' => $response->status(),
            ]);

            return [];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return [];
        }

        // { messages: { records: [...], total: N } }  ← Evolution API v2 paginado
        if (isset($body['messages']['records']) && is_array($body['messages']['records'])) {
            return $body['messages']['records'];
        }

        // { messages: [...] }  ← formato flat confirmado
        if (isset($body['messages']) && is_array($body['messages'])) {
            $msgs = $body['messages'];
            // Se for array indexado (lista de mensagens), retorna diretamente
            if (empty($msgs) || isset($msgs[0])) {
                return $msgs;
            }
        }

        // { records: [...] }  ← variante alternativa
        if (isset($body['records']) && is_array($body['records'])) {
            return $body['records'];
        }

        // Array raiz diretamente
        if (isset($body[0])) {
            return $body;
        }

        \Log::debug('FETCH_MESSAGES_UNKNOWN_FORMAT', [
            'instance' => $instance,
            'jid' => $remoteJid,
            'keys' => array_keys($body),
        ]);

        return [];
    }

    /**
     * A Evolution já retornou coleções em formatos diferentes entre versões e
     * provedores: lista na raiz, { records: [] }, { data: [] } ou um contêiner
     * específico como { contacts: { records: [] } }. Normaliza todos eles aqui.
     */
    private function extrairRegistros(mixed $body, array $containers = []): array
    {
        if (! is_array($body)) {
            return [];
        }

        if (array_is_list($body)) {
            return $body;
        }

        foreach ([...$containers, 'data'] as $container) {
            $value = $body[$container] ?? null;

            if (! is_array($value)) {
                continue;
            }

            if (isset($value['records']) && is_array($value['records'])) {
                return $value['records'];
            }

            if (array_is_list($value)) {
                return $value;
            }
        }

        if (isset($body['records']) && is_array($body['records'])) {
            return $body['records'];
        }

        return [];
    }

    public function fetchMedia(string $instance, string $messageId, bool $fromMe, string $remoteJid): ?array
    {
        $response = $this->http(20)
            ->post("{$this->baseUrl}/chat/getBase64FromMediaMessage/{$instance}", [
                'message' => [
                    'key' => [
                        'id' => $messageId,
                        'fromMe' => $fromMe,
                        'remoteJid' => $remoteJid,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function desconectar(string $instance): bool
    {
        $response = $this->http()
            ->delete("{$this->baseUrl}/instance/logout/{$instance}");

        return $response->successful();
    }

    public function configurarWebhook(string $instance, string $webhookUrl, ?string $webhookToken = null): bool
    {
        // Evolution API v2 requires nested 'webhook' object
        $response = $this->http()
            ->post("{$this->baseUrl}/webhook/set/{$instance}", [
                'webhook' => [
                    'enabled' => true,
                    'url' => $webhookUrl,
                    'headers' => $webhookToken ? ['X-Webhook-Token' => $webhookToken] : [],
                    'byEvents' => false,
                    'base64' => false,
                    'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE', 'CHATS_UPSERT', 'CONTACTS_UPSERT'],
                ],
            ]);

        return $response->successful();
    }
}
