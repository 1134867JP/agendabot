<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AsaasService
{
    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'access_token' => config('services.asaas.key'),
            'Content-Type' => 'application/json',
        ])->baseUrl(config('services.asaas.base_url'));
    }

    public function criarOuBuscarCliente(User $user, Tenant $tenant): string
    {
        if ($tenant->asaas_customer_id) {
            return $tenant->asaas_customer_id;
        }

        $response = $this->http()->post('/customers', [
            'name'              => $user->name,
            'email'             => $user->email,
            'externalReference' => "tenant_{$tenant->id}",
        ]);

        $customerId = $response->json('id');
        $tenant->update(['asaas_customer_id' => $customerId]);

        return $customerId;
    }

    public function criarAssinatura(string $customerId, string $plano, string $paymentMethod = 'CREDIT_CARD'): array
    {
        $valor = config("plans.{$plano}.valor");

        $response = $this->http()->post('/subscriptions', [
            'customer'          => $customerId,
            'billingType'       => $paymentMethod,
            'value'             => $valor,
            'nextDueDate'       => now()->addDays((int) env('TRIAL_DAYS', 14))->format('Y-m-d'),
            'cycle'             => 'MONTHLY',
            'description'       => 'AgendaBot — Plano ' . ucfirst($plano),
            'externalReference' => "plano_{$plano}",
        ]);

        return $response->json();
    }

    public function gerarLinkCheckout(string $subscriptionId): ?string
    {
        $response = $this->http()->get("/subscriptions/{$subscriptionId}/paymentLink");
        return $response->json('url');
    }

    public function cancelarAssinatura(string $subscriptionId): bool
    {
        $response = $this->http()->delete("/subscriptions/{$subscriptionId}");
        return $response->successful();
    }

    public function statusAssinatura(string $subscriptionId): string
    {
        $response = $this->http()->get("/subscriptions/{$subscriptionId}");
        return $response->json('status') ?? 'desconhecido';
    }
}
