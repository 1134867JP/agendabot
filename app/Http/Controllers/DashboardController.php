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
        $user    = $request->user();
        $tenants = $user->tenants()->get();

        if ($tenants->count() === 1) {
            session(['tenant_id' => $tenants->first()->id]);
            return redirect()->route('tenant.dashboard');
        }

        if ($tenants->count() === 0) {
            return redirect()->route('onboarding.step1');
        }

        return Inertia::render('Dashboard', [
            'tenants' => $tenants,
        ]);
    }
}
