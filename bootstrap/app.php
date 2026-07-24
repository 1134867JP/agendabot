<?php

use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureHasTenant;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\LimitRequestBody;
use App\Http\Middleware\ValidateMonitorToken;
use App\Support\ErrorAlerter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1,172.16.0.0/12')),
        )));
        $middleware->trustProxies(at: $trustedProxies);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhook/*',
            'asaas/webhook',
        ]);

        $middleware->alias([
            'tenant' => EnsureHasTenant::class,
            'tenant.admin' => EnsureTenantAdmin::class,
            'superadmin' => EnsureSuperAdmin::class,
            'subscription' => CheckSubscription::class,
            'monitor.token' => ValidateMonitorToken::class,
            'body.limit' => LimitRequestBody::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Por padrão o Laravel silencia ModelNotFoundException (404) e não grava em log.
        // Aqui isso quase sempre indica um bug real (ex.: usuário sem tenant tentando
        // acessar rota que assume tenant existente), então passa a registrar em log.
        $exceptions->stopIgnoring(ModelNotFoundException::class);

        // Alerta por e-mail (ERROR_ALERT_EMAIL) para exceções não tratadas em produção,
        // além do log padrão. Exclui erros esperados (validação, 404, auth, CSRF) para
        // não gerar ruído — só dispara para o que é, de fato, bug.
        $exceptions->report(function (Throwable $e): void {
            if (! app()->environment('production')) {
                return;
            }

            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof TokenMismatchException
                || $e instanceof HttpExceptionInterface) {
                return;
            }

            ErrorAlerter::avisar($e);
        });
    })->create();
