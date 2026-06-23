<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class VerificarTrialExpiradoJob
{
    use Dispatchable;

    public function handle(): void
    {
        Tenant::where('subscription_status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->each(function (Tenant $tenant) {
                $tenant->update(['subscription_status' => 'expired']);

                // Notificar o owner por e-mail
                $owner = $tenant->users()->wherePivot('papel', 'admin')->first();
                if ($owner) {
                    // \Mail::to($owner->email)->send(new \App\Mail\TrialExpiradoMail($tenant));
                    Log::info("Trial expirado: tenant #{$tenant->id} {$tenant->nome}");
                }
            });
    }
}
