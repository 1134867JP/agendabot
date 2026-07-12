<?php

namespace App\Http\Controllers;

use App\Services\AsaasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function renovar(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/Renovar', [
            'tenant' => $tenant,
            'plano' => config("plans.{$tenant->plano}"),
        ]);
    }

    public function processarRenovacao(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $linkPagamento = app(AsaasService::class)
            ->gerarLinkCheckout($tenant->asaas_subscription_id ?? '');

        if ($linkPagamento) {
            return redirect($linkPagamento);
        }

        return redirect()->route('tenant.renovar')
            ->with('erro', 'Não foi possível gerar o link de pagamento. Entre em contato com o suporte.');
    }

    public function cancelar(): RedirectResponse
    {
        $tenant = app('tenant');

        if ($tenant->asaas_subscription_id) {
            app(AsaasService::class)->cancelarAssinatura($tenant->asaas_subscription_id);
        }

        $tenant->update(['subscription_status' => 'canceled']);

        return redirect()->route('tenant.renovar')
            ->with('info', 'Assinatura cancelada. Seus dados ficam disponíveis por 30 dias.');
    }
}
