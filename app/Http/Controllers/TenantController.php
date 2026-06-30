<?php

namespace App\Http\Controllers;

use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function __construct(private EvolutionApiService $evolution) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'                       => ['required', 'string', 'max:255'],
            'tipo_servico'               => ['required', 'in:barbeiro,quadra,estetica,clinica,studio,personalizado'],
            'tipo_servico_personalizado' => ['nullable', 'required_if:tipo_servico,personalizado', 'string', 'max:100'],
        ]);

        $slug     = Str::slug($data['nome']) . '-' . Str::random(6);
        $instance = $slug;

        $tenant = DB::transaction(function () use ($data, $slug, $instance, $request) {
            $t = Tenant::create([
                'nome'                       => $data['nome'],
                'slug'                       => $slug,
                'tipo_servico'               => $data['tipo_servico'],
                'tipo_servico_personalizado' => $data['tipo_servico_personalizado'] ?? null,
                'evolution_instance'         => $instance,
                'subscription_status'        => 'trial',
                'trial_ends_at'              => now()->addDays((int) env('TRIAL_DAYS', 14)),
                'ativo'                      => true,
            ]);
            $t->users()->attach($request->user()->id, ['papel' => 'admin']);
            return $t;
        });

        CreateEvolutionInstanceJob::dispatch($tenant);

        session(['tenant_id' => $tenant->id]);

        return redirect()->route('tenant.dashboard');
    }

    public function selecionar(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user()->tenants()->where('tenants.id', $tenant->id)->exists(), 403);

        session(['tenant_id' => $tenant->id]);

        return redirect()->route('tenant.dashboard');
    }
}
