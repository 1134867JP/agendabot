<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VerificarTrialExpiradoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Tenant::where('subscription_status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->each(function (Tenant $tenant) {
                $tenant->update(['subscription_status' => 'blocked']);

                // Notificar o owner por e-mail
                $owner = $tenant->users()->wherePivot('papel', 'admin')->first();
                if ($owner) {
                    // \Mail::to($owner->email)->send(new \App\Mail\TrialExpiradoMail($tenant));
                    Log::info("Trial expirado: tenant #{$tenant->id} {$tenant->nome}");
                }
            });
    }
}
