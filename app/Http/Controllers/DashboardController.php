<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->is_super_admin) {
            return redirect()->route('superadmin.dashboard');
        }

        $tenants = $user->tenants()->wherePivot('ativo', true)->get();

        if ($tenants->count() === 1 && $tenants->first()->ativo) {
            session(['tenant_id' => $tenants->first()->id]);

            return redirect()->route('tenant.dashboard');
        }

        return Inertia::render('Dashboard', [
            'tenants' => $tenants,
        ]);
    }
}
