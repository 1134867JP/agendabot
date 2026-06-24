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

        $isImpersonating = session()->has('impersonando_tenant_id');
        $user = $request->user();

        // Tenant inexistente ou sessão obsoleta → limpar e redirecionar
        if (! $tenant) {
            session()->forget('tenant_id');
            return redirect()->route('dashboard')->with('erro', 'Estabelecimento não encontrado. Selecione novamente.');
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
        View::share('currentTenant', $tenant);

        return $next($request);
    }
}
