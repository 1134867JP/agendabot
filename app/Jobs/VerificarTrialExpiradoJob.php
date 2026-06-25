<?php

namespace App\Jobs;

use App\Mail\TrialExpiradoMail;
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

    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    public int $timeout = 60;

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
