<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\CreateEvolutionInstanceJob;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));

            $request->user()->tenants()
                ->where('tenants.whatsapp_conectado', false)
                ->each(fn ($tenant) => CreateEvolutionInstanceJob::dispatch($tenant)->onQueue('sync'));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
