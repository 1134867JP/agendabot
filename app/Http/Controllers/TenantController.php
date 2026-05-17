<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function __construct(private EvolutionApiService $evolution) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'         => ['required', 'string', 'max:255'],
            'tipo_servico' => ['required', 'in:barbeiro,quadra,estetica,personalizado'],
        ]);

        $slug     = Str::slug($data['nome']);
        $instance = $slug . '-instance';

        $tenant = Tenant::create([
            'nome'               => $data['nome'],
            'slug'               => $slug,
            'tipo_servico'       => $data['tipo_servico'],
            'evolution_instance' => $instance,
        ]);

        $tenant->users()->attach($request->user()->id, ['papel' => 'admin']);

        $this->evolution->criarInstancia($instance);
        $this->evolution->configurarWebhook($instance, route('webhook', $slug));

        return redirect()->route('dashboard');
    }

    public function selecionar(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user()->tenants->contains($tenant), 403);

        session(['tenant_id' => $tenant->id]);

        return redirect()->route('tenant.dashboard');
    }
}
