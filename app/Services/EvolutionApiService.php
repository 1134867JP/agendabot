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
        $found      = $instancias->firstWhere('instance.instanceName', $instance);

        return $found['instance']['state'] ?? 'desconhecido';
    }

    public function configurarWebhook(string $instance, string $webhookUrl): bool
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->post("{$this->baseUrl}/webhook/set/{$instance}", [
                'url'     => $webhookUrl,
                'enabled' => true,
                'events'  => ['MESSAGES_UPSERT'],
            ]);

        return $response->successful();
    }
}
