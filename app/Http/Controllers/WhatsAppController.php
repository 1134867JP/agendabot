<?php

namespace App\Http\Controllers;

use App\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class WhatsAppController extends Controller
{
    public function __construct(private EvolutionApiService $evolution) {}

    public function qrcode(): JsonResponse
    {
        $tenant  = app('tenant');
        $qrcode  = $this->evolution->obterQrCode($tenant->evolution_instance);

        return response()->json(['qrcode' => $qrcode]);
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

    public function conectar(): RedirectResponse
    {
        $tenant   = app('tenant');
        $instance = $tenant->evolution_instance;

        $this->evolution->criarInstancia($instance);
        $this->evolution->configurarWebhook($instance, route('webhook', $tenant->slug));

        return back();
    }
}
