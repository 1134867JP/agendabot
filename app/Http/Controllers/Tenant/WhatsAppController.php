<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function __construct(private EvolutionApiService $evolution) {}

    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/WhatsApp', [
            'tenant'       => $tenant,
            'webhook_url'  => route('webhook', $tenant->slug),
        ]);
    }

    public function qrcode(): JsonResponse
    {
        $tenant   = app('tenant');
        $instance = $tenant->evolution_instance;

        if (!$instance) {
            $instance = $tenant->slug . '-instance';
            $tenant->update(['evolution_instance' => $instance]);
        }

        $status = $this->evolution->statusInstancia($instance);

        // Já conectado — garantir webhook atualizado e avisar o frontend
        if ($status === 'open') {
            $tenant->update(['whatsapp_conectado' => true]);
            $this->evolution->configurarWebhook($instance, route('webhook', $tenant->slug));
            return response()->json(['connected' => true]);
        }

        // Instância não existe — criar e configurar webhook
        if ($status === 'desconhecido') {
            $result = $this->evolution->criarInstancia($instance);
            $this->evolution->configurarWebhook($instance, route('webhook', $tenant->slug));

            $qrcode = data_get($result, 'qrcode.base64') ?? data_get($result, 'base64');
            if ($qrcode) {
                return response()->json(['qrcode' => $qrcode]);
            }
        }

        // Instância existe mas não conectada — garantir webhook atualizado e buscar QR
        $this->evolution->configurarWebhook($instance, route('webhook', $tenant->slug));
        $qrcode = $this->evolution->obterQrCode($instance);
        return response()->json(['qrcode' => $qrcode]);
    }

    public function desconectar(): JsonResponse
    {
        $tenant = app('tenant');

        if (!$tenant->evolution_instance) {
            return response()->json(['ok' => false, 'erro' => 'Instância não configurada.'], 400);
        }

        $ok = $this->evolution->desconectar($tenant->evolution_instance);

        $tenant->update(['whatsapp_conectado' => false]);

        return response()->json(['ok' => $ok]);
    }

    public function status(): JsonResponse
    {
        $tenant = app('tenant');
        $status = $this->evolution->statusInstancia($tenant->evolution_instance);

        $conectado = $status === 'open';
        if ($conectado !== $tenant->whatsapp_conectado) {
            $tenant->update(['whatsapp_conectado' => $conectado]);
        }

        return response()->json(['status' => $status]);
    }
}
