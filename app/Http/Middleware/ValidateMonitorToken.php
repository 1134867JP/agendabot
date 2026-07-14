<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateMonitorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.monitor.token');
        $recebido = (string) $request->bearerToken();

        if ($token === '' || ! hash_equals($token, $recebido)) {
            abort(401);
        }

        return $next($request);
    }
}
