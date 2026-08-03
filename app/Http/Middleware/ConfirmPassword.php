<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfirmPassword extends RequirePassword
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if ($request->routeIs('superadmin.tenants.impersonar')) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
