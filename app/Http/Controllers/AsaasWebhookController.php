<?php

namespace App\Http\Controllers;

use App\Mail\AssinaturaPendenteMail;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class AsaasWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = (string) config('services.asaas.webhook_secret', '');
        $token  = (string) $request->header('asaas-access-token', '');
        if ($secret === '' || ! hash_equals($secret, $token)) {
            return response('Unauthorized', 401);
        }

        $event   = $request->input('event');
        $payment = $request->input('payment', []);

        $customerId = data_get($payment, 'customer');
        $tenant     = Tenant::where('asaas_customer_id', $customerId)->first();

        if (! $tenant) {
            return response('ok');
        }

        SubscriptionEvent::create([
            'tenant_id'        => $tenant->id,
            'tipo'             => $event,
            'asaas_payment_id' => data_get($payment, 'id'),
            'valor'            => data_get($payment, 'value'),
            'payload'          => $request->all(),
        ]);

        match ($event) {
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->ativar($tenant),
            'PAYMENT_OVERDUE'                       => $this->marcarVencido($tenant),
            'SUBSCRIPTION_DELETED'                  => $this->cancelar($tenant),
            default                                 => null,
        };

        return response('ok');
    }

    private function ativar(Tenant $tenant): void
    {
        $tenant->update([
            'subscription_status'  => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ]);
    }

    private function marcarVencido(Tenant $tenant): void
    {
        $tenant->update([
            'subscription_status'  => 'past_due',
            'subscription_ends_at' => now(),
        ]);

        $dono = $tenant->users()->first();
        if ($dono) {
            Mail::to($dono->email)->queue(new AssinaturaPendenteMail($tenant));
        }
    }

    private function cancelar(Tenant $tenant): void
    {
        $tenant->update(['subscription_status' => 'canceled']);
    }
}
