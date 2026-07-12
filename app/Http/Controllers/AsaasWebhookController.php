<?php

namespace App\Http\Controllers;

use App\Mail\AssinaturaPendenteMail;
use App\Models\Agendamento;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AsaasWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = (string) config('services.asaas.webhook_secret', '');
        $token = (string) $request->header('asaas-access-token', '');
        if ($secret === '' || ! hash_equals($secret, $token)) {
            return response('Unauthorized', 401);
        }

        $event = $request->input('event');
        $payment = $request->input('payment', []);

        $externalReference = (string) data_get($payment, 'externalReference', '');
        if (preg_match('/^deposit_agendamento_(\d+)$/', $externalReference, $matches)) {
            $agendamento = Agendamento::find((int) $matches[1]);
            if ($agendamento && in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)) {
                $agendamento->update(['deposit_status' => 'paid']);
            }

            return response('ok');
        }

        $customerId = data_get($payment, 'customer');
        $tenant = Tenant::where('asaas_customer_id', $customerId)->first();

        if (! $tenant) {
            return response('ok');
        }

        // O Asaas pode reenviar o mesmo webhook. O ID oficial é preferido; para
        // payloads antigos/sem ID, o hash do corpo mantém o processamento idempotente.
        $eventId = (string) $request->input('id', '');
        if ($eventId === '') {
            $eventId = 'sha256:'.hash('sha256', $request->getContent());
        }

        DB::transaction(function () use ($tenant, $event, $eventId, $payment, $request): void {
            $inserido = DB::table('subscription_events')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'event_id' => $eventId,
                'tipo' => $event,
                'asaas_payment_id' => data_get($payment, 'id'),
                'valor' => data_get($payment, 'value'),
                'payload' => json_encode($request->all(), JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserido === 0) {
                return;
            }

            match ($event) {
                'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->ativar($tenant),
                'PAYMENT_OVERDUE' => $this->marcarVencido($tenant),
                'SUBSCRIPTION_DELETED' => $this->cancelar($tenant),
                default => null,
            };
        });

        return response('ok');
    }

    private function ativar(Tenant $tenant): void
    {
        $tenant->update([
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ]);
    }

    private function marcarVencido(Tenant $tenant): void
    {
        $tenant->update([
            'subscription_status' => 'past_due',
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
