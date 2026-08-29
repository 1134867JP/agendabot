<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = session('tenant_id');

        if (! $tenantId) {
            return redirect()->route('dashboard')
                ->with('erro', 'Selecione um estabelecimento.');
        }

        $tenant = Tenant::find($tenantId);

        $user = $request->user();
        $isImpersonating = session()->has('impersonando_tenant_id') && (bool) $user?->is_super_admin;

        // Tenant inexistente ou sessão obsoleta → limpar e redirecionar
        if (! $tenant) {
            session()->forget('tenant_id');

            return redirect()->route('dashboard')->with('erro', 'Estabelecimento não encontrado. Selecione novamente.');
        }

        // A desativação precisa bloquear o painel, não apenas os webhooks. O super
        // admin continua podendo entrar para diagnóstico e reativação do tenant.
        if (! $tenant->ativo && ! $user?->is_super_admin) {
            session()->forget('tenant_id');

            return redirect()->route('dashboard')->with('erro', 'Este estabelecimento está desativado. Fale com o suporte para reativá-lo.');
        }

        // Verificar acesso via query explícita (mais confiável que Collection::contains)
        $temAcesso = $isImpersonating
            || $user->is_super_admin
            || $user->tenants()->where('tenants.id', $tenant->id)->exists();

        if (! $temAcesso) {
            session()->forget('tenant_id');

            return redirect()->route('dashboard')->with('erro', 'Você não tem acesso a este estabelecimento.');
        }

        app()->instance('tenant', $tenant);
        config(['app.timezone' => $tenant->resolvedTimezone()]);
        View::share('currentTenant', $tenant);

        return $next($request);
    }
}
