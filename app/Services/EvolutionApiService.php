<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class EvolutionApiService
{
    private string $baseUrl;
    private string $globalApiKey;

    public function __construct()
    {
        $this->baseUrl      = (string) config('services.evolution.url');
        $this->globalApiKey = (string) config('services.evolution.key');
    }

    public function enviarMensagem(string $instance, string $telefone, string $mensagem): bool
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->post("{$this->baseUrl}/message/sendText/{$instance}", [
                'number' => $telefone,
                'text'   => $mensagem,
            ]);

        return $response->successful();
    }

    public function criarInstancia(string $instanceName): array
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->post("{$this->baseUrl}/instance/create", [
                'instanceName' => $instanceName,
                'integration'  => 'WHATSAPP-BAILEYS',
                'qrcode'       => true,
            ]);

        return $response->json() ?? [];
    }

    public function obterQrCode(string $instance): ?string
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->get("{$this->baseUrl}/instance/connect/{$instance}");

        return $response->json('base64');
    }

    public function statusInstancia(string $instance): string
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->get("{$this->baseUrl}/instance/fetchInstances");

        $instancias = collect($response->json() ?? []);

        // Evolution API v2: flat array with 'name' and 'connectionStatus'
        $found = $instancias->firstWhere('name', $instance);

        return $found['connectionStatus'] ?? 'desconhecido';
        // open = conectado | close = desconectado | connecting = aguardando
    }

    public function fetchChats(string $instance): array
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->post("{$this->baseUrl}/chat/findChats/{$instance}", []);

        return $response->json() ?? [];
    }

    public function fetchContacts(string $instance): array
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->post("{$this->baseUrl}/chat/findContacts/{$instance}", []);

        return $response->json() ?? [];
    }

    public function fetchMessages(string $instance, string $remoteJid, int $count = 100): array
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/findMessages/{$instance}", [
                'where' => ['key' => ['remoteJid' => $remoteJid]],
                'limit' => $count,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $body = $response->json();

        // Suporta diferentes formatos de resposta da Evolution API v1/v2
        if (isset($body['messages']['records'])) {
            return $body['messages']['records'];           // v2: {messages:{records:[]}}
        }
        if (isset($body['messages']) && is_array($body['messages'])) {
            return $body['messages'];                      // variante: {messages:[]}
        }
        if (isset($body['records']) && is_array($body['records'])) {
            return $body['records'];                       // variante: {records:[]}
        }
        if (is_array($body) && isset($body[0])) {
            return $body;                                  // array direto
        }

        return [];
    }

    public function fetchMedia(string $instance, string $messageId, bool $fromMe, string $remoteJid): ?array
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->timeout(20)
            ->post("{$this->baseUrl}/chat/getBase64FromMediaMessage/{$instance}", [
                'message' => [
                    'key' => [
                        'id'        => $messageId,
                        'fromMe'    => $fromMe,
                        'remoteJid' => $remoteJid,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function configurarWebhook(string $instance, string $webhookUrl): bool
    {
        // Evolution API v2 requires nested 'webhook' object
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->post("{$this->baseUrl}/webhook/set/{$instance}", [
                'webhook' => [
                    'enabled'        => true,
                    'url'            => $webhookUrl,
                    'byEvents'       => false,
                    'base64'         => false,
                    'events'         => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE'],
                ],
            ]);

        return $response->successful();
    }
}
