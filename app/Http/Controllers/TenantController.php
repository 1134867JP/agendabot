<?php

namespace App\Http\Controllers;

use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\Tenant;
use App\Services\EvolutionApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(private EvolutionApiService $evolution) {}

    /**
     * Fluxo dedicado para um usuário JÁ autenticado adicionar mais um estabelecimento
     * (distinto do onboarding, que cria usuário + tenant juntos).
     */
    public function create(): Response
    {
        return Inertia::render('Estabelecimentos/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->tenants()->exists()) {
            throw ValidationException::withMessages([
                'nome' => 'O cadastro público permite um estabelecimento por conta. Fale com o suporte para adicionar outra unidade.',
            ]);
        }

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'tipo_servico' => ['required', Rule::in(Tenant::TIPOS_SERVICO)],
            'tipo_servico_personalizado' => ['nullable', 'required_if:tipo_servico,personalizado', 'string', 'max:100'],
        ]);

        $slug = Str::slug($data['nome']).'-'.Str::random(6);
        $instance = $slug;

        $tenant = DB::transaction(function () use ($data, $slug, $instance, $request) {
            $t = Tenant::create([
                'nome' => $data['nome'],
                'slug' => $slug,
                'tipo_servico' => $data['tipo_servico'],
                'tipo_servico_personalizado' => $data['tipo_servico_personalizado'] ?? null,
                'evolution_instance' => $instance,
                'webhook_token' => Str::random(32),
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays((int) config('services.trial_days', env('TRIAL_DAYS', 14))),
                'ativo' => true,
            ]);
            $t->users()->attach($request->user()->id, ['papel' => 'admin']);

            return $t;
        });

        CreateEvolutionInstanceJob::dispatch($tenant)->onQueue('sync');

        session(['tenant_id' => $tenant->id]);

        return redirect()->route('tenant.dashboard');
    }

    public function selecionar(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user()->tenants()->where('tenants.id', $tenant->id)->exists(), 403);
        abort_unless($tenant->ativo, 403);

        session(['tenant_id' => $tenant->id]);

        return redirect()->route('tenant.dashboard');
    }
}
