<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return $next($request);
        }

        if ($tenant->subscription_status === 'trial') {
            if ($tenant->trial_ends_at && now()->isAfter($tenant->trial_ends_at)) {
                $tenant->update(['subscription_status' => 'past_due', 'subscription_ends_at' => $tenant->trial_ends_at]);
            } else {
                return $next($request);
            }
        }

        if ($tenant->subscription_status === 'active') {
            return $next($request);
        }

        if ($tenant->subscription_status === 'past_due') {
            $diasVencido = $tenant->subscription_ends_at
                ? (int) now()->diffInDays($tenant->subscription_ends_at)
                : 999;

            if ($diasVencido <= 3) {
                return $next($request);
            }

            $tenant->update(['subscription_status' => 'blocked']);
        }

        if (in_array($tenant->subscription_status, ['blocked', 'canceled'])) {
            if ($request->routeIs('tenant.renovar*')) {
                return $next($request);
            }
            return redirect()->route('tenant.renovar');
        }

        return $next($request);
    }
}
