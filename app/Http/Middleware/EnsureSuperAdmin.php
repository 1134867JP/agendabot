<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_super_admin) {
            abort(403);
        }

        if (config('auth.superadmin_two_factor')) {
            $verifiedAt = (int) $request->session()->get('superadmin_2fa_verified_at', 0);
            $stillValid = $verifiedAt > 0
                && $verifiedAt >= now()->subHours((int) config('auth.superadmin_two_factor_hours', 8))->timestamp;

            if (! $stillValid) {
                $request->session()->put('url.intended', $request->fullUrl());

                return redirect()->route('superadmin.two-factor.challenge');
            }
        }

        return $next($request);
    }
}
