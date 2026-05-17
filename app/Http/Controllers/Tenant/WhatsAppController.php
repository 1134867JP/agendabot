<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $tenant = app('tenant');
        $qrcode = $this->evolution->obterQrCode($tenant->evolution_instance);
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
}
