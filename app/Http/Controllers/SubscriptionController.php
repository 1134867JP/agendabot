<?php

namespace App\Http\Controllers;

use App\Exceptions\AsaasApiException;
use App\Services\AsaasService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function renovar(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/Renovar', [
            'tenant' => $tenant,
            'planos' => config('plans'),
            'planoAtual' => $tenant->plano,
        ]);
    }

    public function processarRenovacao(Request $request, AsaasService $asaas): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'plano' => 'required|in:starter,pro,business',
            'ciclo' => 'required|in:mensal,anual',
            'metodo' => 'required|in:CREDIT_CARD,PIX',
        ]);

        $tenant = app('tenant');

        try {
            $checkoutKey = implode(':', [
                'asaas', 'renewal', $tenant->id,
                $validated['plano'], $validated['ciclo'], $validated['metodo'],
            ]);

            $url = Cache::lock("{$checkoutKey}:lock", 45)->block(5, fn (): string => Cache::remember(
                "{$checkoutKey}:url",
                now()->addMinutes(15),
                fn (): string => $asaas->criarCheckoutRenovacao(
                    $request->user(),
                    $tenant,
                    $validated['plano'],
                    $validated['ciclo'],
                    $validated['metodo'],
                ),
            ));

            $tenant->update([
                'plano' => $validated['plano'],
                'taxa_agendamento_bot' => 0,
            ]);

            // O POST é feito pelo Inertia via Axios. Um redirect 302 externo
            // faria o Axios seguir o checkout do Asaas dentro do XHR e falhar
            // por CORS. Inertia::location força uma navegação do navegador.
            return Inertia::location($url);
        } catch (AsaasApiException|ConnectionException|LockTimeoutException $e) {
            Log::channel('jobs')->error('Falha ao gerar renovação no Asaas', [
                'tenant_id' => $tenant->id,
                'erro' => $e->getMessage(),
            ]);

            return redirect()->route('tenant.renovar')
                ->with('erro', 'Não foi possível abrir o pagamento. Tente novamente em alguns instantes.');
        }
    }

    public function cancelar(): RedirectResponse
    {
        $tenant = app('tenant');

        try {
            if ($tenant->asaas_subscription_id) {
                app(AsaasService::class)->cancelarAssinatura($tenant->asaas_subscription_id);
            }
        } catch (AsaasApiException|ConnectionException $e) {
            Log::channel('jobs')->error('Falha ao cancelar assinatura no Asaas', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $tenant->asaas_subscription_id,
                'erro' => $e->getMessage(),
            ]);

            return back()->with('erro', 'O cancelamento não foi confirmado pelo meio de pagamento. Nenhuma alteração foi feita; tente novamente ou fale com o suporte.');
        }

        $tenant->update([
            'subscription_status' => 'canceled',
            'asaas_subscription_id' => null,
        ]);

        return redirect()->route('tenant.renovar')
            ->with('info', 'Assinatura cancelada. Seus dados ficam disponíveis por 30 dias.');
    }
}
