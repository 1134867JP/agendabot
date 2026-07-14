<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Webhook routes sem nenhum middleware (sem session, sem Inertia, sem CSRF)
            require __DIR__.'/../routes/webhooks.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhook/*',
            'asaas/webhook',
        ]);

        $middleware->alias([
            'tenant'       => \App\Http\Middleware\EnsureHasTenant::class,
            'superadmin'   => \App\Http\Middleware\EnsureSuperAdmin::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Por padrão o Laravel silencia ModelNotFoundException (404) e não grava em log.
        // Aqui isso quase sempre indica um bug real (ex.: usuário sem tenant tentando
        // acessar rota que assume tenant existente), então passa a registrar em log.
        $exceptions->stopIgnoring(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    })->create();
