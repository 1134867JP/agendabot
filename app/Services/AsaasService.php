<?php

namespace App\Services;

use App\Exceptions\AsaasApiException;
use App\Models\Agendamento;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AsaasService
{
    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'access_token' => config('services.asaas.key'),
            'Content-Type' => 'application/json',
        ])->baseUrl(config('services.asaas.base_url'))
            ->connectTimeout(5)
            ->timeout(15);
    }

    public function criarOuBuscarCliente(User $user, Tenant $tenant): string
    {
        return Cache::lock("asaas:customer:tenant:{$tenant->id}", 30)->block(5, function () use ($user, $tenant): string {
            $tenant->refresh();
            if ($tenant->asaas_customer_id) {
                return $tenant->asaas_customer_id;
            }

            $response = $this->http()->post('/customers', [
                'name' => $user->name,
                'email' => $user->email,
                'externalReference' => "tenant_{$tenant->id}",
            ]);

            $customerId = $response->json('id');

            if (! $response->successful() || ! is_string($customerId) || $customerId === '') {
                throw new AsaasApiException('Não foi possível criar o cliente no Asaas: '.$response->body());
            }

            $tenant->update(['asaas_customer_id' => $customerId]);

            return $customerId;
        });
    }

    public function criarAssinatura(string $customerId, string $plano, string $paymentMethod = 'CREDIT_CARD'): ?array
    {
        $valor = config("plans.{$plano}.valor");

        $response = $this->http()->post('/subscriptions', [
            'customer' => $customerId,
            'billingType' => $paymentMethod,
            'value' => $valor,
            'nextDueDate' => now()->addDays((int) env('TRIAL_DAYS', 14))->format('Y-m-d'),
            'cycle' => 'MONTHLY',
            'description' => 'Agendou — Plano '.ucfirst($plano),
            'externalReference' => "plano_{$plano}",
        ]);

        if (! $response->successful()) {
            throw new AsaasApiException('Falha ao criar assinatura no Asaas: '.$response->body());
        }

        return $response->json();
    }

    public function gerarLinkCheckout(string $subscriptionId): ?string
    {
        $response = $this->http()->get("/subscriptions/{$subscriptionId}/paymentLink");

        return $response->json('url');
    }

    /**
     * Gera o pagamento de renovação. Cartão cria recorrência automática;
     * Pix cria uma cobrança do período escolhido e deverá ser renovado ao vencer.
     */
    public function criarCheckoutRenovacao(User $user, Tenant $tenant, string $plano, string $ciclo, string $metodo): string
    {
        $customerId = $this->criarOuBuscarCliente($user, $tenant);
        $anual = $ciclo === 'anual';
        $valorMensal = (float) config("plans.{$plano}.valor");
        $valor = $anual ? round($valorMensal * 10, 2) : $valorMensal;
        $cycle = $anual ? 'YEARLY' : 'MONTHLY';
        $externalReference = "assinatura_tenant_{$tenant->id}_{$plano}_{$ciclo}";
        $descricao = 'AgendaBot — Plano '.ucfirst($plano).($anual ? ' anual' : ' mensal');

        if ($metodo === 'PIX') {
            $response = $this->http()->post('/payments', [
                'customer' => $customerId,
                'billingType' => 'PIX',
                'value' => $valor,
                'dueDate' => now()->format('Y-m-d'),
                'description' => $descricao,
                'externalReference' => $externalReference,
            ]);

            $url = $response->json('invoiceUrl');
            if (! $response->successful() || ! is_string($url) || $url === '') {
                throw new AsaasApiException('Não foi possível gerar a cobrança Pix: '.$response->body());
            }

            return $url;
        }

        $assinaturaAnterior = $tenant->asaas_subscription_id;

        $response = $this->http()->post('/subscriptions', [
            'customer' => $customerId,
            'billingType' => 'CREDIT_CARD',
            'value' => $valor,
            'nextDueDate' => now()->format('Y-m-d'),
            'cycle' => $cycle,
            'description' => $descricao,
            'externalReference' => $externalReference,
        ]);

        $subscriptionId = $response->json('id');
        if (! $response->successful() || ! is_string($subscriptionId) || $subscriptionId === '') {
            throw new AsaasApiException('Não foi possível criar a assinatura: '.$response->body());
        }

        $payments = $this->http()->get("/subscriptions/{$subscriptionId}/payments", ['limit' => 1]);
        $url = $payments->json('data.0.invoiceUrl');
        if (! $payments->successful() || ! is_string($url) || $url === '') {
            throw new AsaasApiException('A assinatura foi criada, mas o checkout não ficou disponível.');
        }

        $tenant->update(['asaas_subscription_id' => $subscriptionId]);

        if ($assinaturaAnterior && $assinaturaAnterior !== $subscriptionId) {
            $this->cancelarAssinatura($assinaturaAnterior);
        }

        return $url;
    }

    public function cancelarAssinatura(string $subscriptionId): void
    {
        $response = $this->http()->delete("/subscriptions/{$subscriptionId}");

        if (! $response->successful()) {
            throw new AsaasApiException('O Asaas não confirmou o cancelamento da assinatura: '.$response->body());
        }
    }

    public function statusAssinatura(string $subscriptionId): string
    {
        $response = $this->http()->get("/subscriptions/{$subscriptionId}");

        return $response->json('status') ?? 'desconhecido';
    }

    /**
     * Cria uma cobrança avulsa (PIX) para a taxa variável mensal de agendamentos via bot.
     * Retorna o ID da cobrança no Asaas, ou null em caso de falha.
     */
    public function criarCobrancaAvulsa(string $customerId, float $valor, string $descricao, Carbon $vencimento): ?string
    {
        $response = $this->http()->post('/payments', [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => round($valor, 2),
            'dueDate' => $vencimento->format('Y-m-d'),
            'description' => $descricao,
            'externalReference' => 'taxa_bot_'.now()->format('Ym').'_'.$customerId,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $id = $response->json('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function criarSinalAgendamento(Agendamento $agendamento, float $valor): array
    {
        $customer = $this->http()->post('/customers', [
            'name' => $agendamento->cliente_nome,
            'mobilePhone' => preg_replace('/\D/', '', (string) $agendamento->cliente_telefone),
            'externalReference' => "appointment_customer_{$agendamento->tenant_id}_{$agendamento->id}",
        ]);

        if (! $customer->successful() || ! $customer->json('id')) {
            throw new AsaasApiException('Não foi possível cadastrar o cliente para cobrança do sinal.');
        }

        $payment = $this->http()->post('/payments', [
            'customer' => $customer->json('id'),
            'billingType' => 'PIX',
            'value' => round($valor, 2),
            'dueDate' => now()->addDay()->format('Y-m-d'),
            'description' => "Sinal do agendamento #{$agendamento->id}",
            'externalReference' => "deposit_agendamento_{$agendamento->id}",
        ]);

        if (! $payment->successful() || ! $payment->json('id')) {
            throw new AsaasApiException('Não foi possível gerar a cobrança do sinal.');
        }

        return [
            'id' => $payment->json('id'),
            'url' => $payment->json('invoiceUrl') ?? $payment->json('bankSlipUrl'),
        ];
    }
}
