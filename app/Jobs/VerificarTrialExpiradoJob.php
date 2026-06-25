<?php

namespace App\Jobs;

use App\Mail\TrialExpiradoMail;
use App\Models\Tenant;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VerificarTrialExpiradoJob
{
    use Dispatchable;

    public function handle(): void
    {
        Tenant::where('subscription_status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->each(function (Tenant $tenant) {
                $tenant->update(['subscription_status' => 'expired']);

                $owner = $tenant->users()->wherePivot('papel', 'admin')->first();
                if ($owner) {
                    Mail::to($owner->email)->queue(new TrialExpiradoMail($tenant));
                    Log::info("Trial expirado: tenant #{$tenant->id} {$tenant->nome} — e-mail enviado para {$owner->email}");
                }
            });
    }
}
