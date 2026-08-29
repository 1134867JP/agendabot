<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Adiciona security headers em todas as respostas do painel web.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $nonce = Vite::cspNonce();
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        $scriptSrc = app()->environment('local')
            ? "'self' 'nonce-{$nonce}' 'unsafe-eval' http://localhost:* http://127.0.0.1:*"
            : "'self' 'nonce-{$nonce}'";
        $connectSrc = app()->environment('local')
            ? "'self' https: wss: http://localhost:* ws://localhost:* http://127.0.0.1:* ws://127.0.0.1:*"
            : "'self' https: wss:";
        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src {$scriptSrc}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src {$connectSrc}",
            "media-src 'self' data: blob: https:",
            "worker-src 'self' blob:",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        // HSTS apenas em HTTPS; secure() respeita somente os proxies confiáveis.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
