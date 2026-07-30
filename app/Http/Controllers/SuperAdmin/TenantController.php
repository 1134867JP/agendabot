<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => Tenant::withCount(['agendamentos', 'recursos'])
                ->with('users')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('SuperAdmin/Tenants/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'tipo_servico' => ['required', 'in:barbeiro,quadra,estetica,clinica,studio,personalizado'],
            'tipo_servico_personalizado' => ['nullable', 'required_if:tipo_servico,personalizado', 'string', 'max:100'],
            'email_dono' => ['required', 'email', 'unique:users,email'],
            'senha_dono' => ['required', 'min:8'],
        ]);

        $tenant = DB::transaction(function () use ($validated): Tenant {
            $slug = Str::slug($validated['nome']).'-'.Str::random(4);

            $webhookToken = Str::random(64);

            $tenant = Tenant::create([
                'nome' => $validated['nome'],
                'slug' => $slug,
                'tipo_servico' => $validated['tipo_servico'],
                'tipo_servico_personalizado' => $validated['tipo_servico_personalizado'] ?? null,
                'evolution_instance' => $slug,
                'webhook_token' => $webhookToken,
            ]);

            $dono = User::create([
                'name' => $validated['nome'],
                'email' => $validated['email_dono'],
                'password' => Hash::make($validated['senha_dono']),
            ]);

            $tenant->users()->attach($dono->id, ['papel' => 'admin']);

            return $tenant;
        });

        CreateEvolutionInstanceJob::dispatch($tenant)->onQueue('sync');

        return redirect()->route('superadmin.tenants.index')
            ->with('success', 'Tenant criado. A conexão do WhatsApp será preparada em segundo plano.');
    }

    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('SuperAdmin/Tenants/Edit', [
            'tenant' => $tenant->load('users'),
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'tipo_servico' => ['required', 'in:barbeiro,quadra,estetica,clinica,studio,personalizado'],
            'tipo_servico_personalizado' => ['nullable', 'required_if:tipo_servico,personalizado', 'string', 'max:100'],
        ]);

        $tenant->update($validated);

        return redirect()->route('superadmin.tenants.index')
            ->with('success', 'Tenant atualizado.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()->route('superadmin.tenants.index')
            ->with('success', 'Tenant removido.');
    }

    public function toggleAtivo(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['ativo' => ! $tenant->ativo]);

        return back()->with('success', $tenant->ativo ? 'Tenant ativado.' : 'Tenant desativado.');
    }

    public function toggleIsento(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['isento_cobranca' => ! $tenant->isento_cobranca]);

        return back()->with('success', $tenant->isento_cobranca ? 'Tenant marcado como isento de cobrança.' : 'Isenção removida.');
    }

    public function impersonar(Request $request, Tenant $tenant): RedirectResponse
    {
        session([
            'impersonando_tenant_id' => $tenant->id,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->route('tenant.dashboard');
    }

    public function pararImpersonar(): RedirectResponse
    {
        session()->forget(['impersonando_tenant_id', 'tenant_id']);

        return redirect()->route('superadmin.dashboard');
    }
}
