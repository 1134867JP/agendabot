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

    private function http(int $timeout = 15): PendingRequest
    {
        return Http::withHeaders(['apikey' => $this->globalApiKey])
            ->timeout($timeout)
            ->connectTimeout(5)
            ->retry(3, 500, throw: false);
    }

    public function configurado(): bool
    {
        return filter_var($this->baseUrl, FILTER_VALIDATE_URL) !== false;
    }

    public function enviarMensagem(string $instance, string $telefone, string $mensagem): bool
    {
        $response = $this->http(10)
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

        if (! $response->successful()) {
            throw EvolutionApiException::requisicaoFalhou('criar instância', $response->status());
        }

        return $response->json() ?? [];
    }

    public function obterQrCode(string $instance): ?string
    {
        $response = $this->http()
            ->get("{$this->baseUrl}/instance/connect/{$instance}");

        return $response->json('base64');
    }

    public function statusInstancia(string $instance): string
    {
        $response = $this->http()
            ->get("{$this->baseUrl}/instance/fetchInstances");

        $instancias = collect($response->json() ?? []);

        // Evolution API v2: flat array with 'name' and 'connectionStatus'
        $found = $instancias->firstWhere('name', $instance);

        return $found['connectionStatus'] ?? 'desconhecido';
        // open = conectado | close = desconectado | connecting = aguardando
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
