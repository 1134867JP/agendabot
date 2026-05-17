<?php

namespace App\Http\Controllers;

use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AsaasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function step1(): Response
    {
        return Inertia::render('Onboarding/Step1');
    }

    public function step1Store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome_usuario'              => 'required|string|max:255',
            'email'                     => 'required|email|unique:users,email',
            'senha'                     => 'required|min:8|confirmed',
            'nome_estabelecimento'      => 'required|string|max:255',
            'tipo_servico'              => 'required|in:barbeiro,quadra,estetica,clinica,studio,personalizado',
            'tipo_servico_personalizado' => 'nullable|required_if:tipo_servico,personalizado|string|max:100',
            'telefone'                  => 'required|string|min:10|max:25',
        ]);

        $user = User::create([
            'name'     => $validated['nome_usuario'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['senha']),
            'telefone' => $validated['telefone'],
        ]);

        $slugInterno = Str::slug($validated['nome_estabelecimento']) . '-' . Str::random(6);

        $tenant = Tenant::create([
            'nome'                       => $validated['nome_estabelecimento'],
            'slug'                       => $slugInterno,
            'tipo_servico'               => $validated['tipo_servico'],
            'tipo_servico_personalizado' => $validated['tipo_servico_personalizado'] ?? null,
            'evolution_instance'         => $slugInterno,
            'subscription_status'        => 'trial',
            'trial_ends_at'              => now()->addDays((int) env('TRIAL_DAYS', 14)),
            'ativo'                      => true,
        ]);

        $tenant->users()->attach($user->id, ['papel' => 'admin']);

        CreateEvolutionInstanceJob::dispatch($tenant);

        Auth::login($user);
        session(['tenant_id' => $tenant->id]);

        return redirect()->route('onboarding.step2');
    }

    public function step2(): Response
    {
        return Inertia::render('Onboarding/Step2', [
            'planos' => config('plans'),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $request->validate([
            'plano' => 'required|in:basico,profissional,ilimitado',
        ]);

        $user   = auth()->user();
        $tenant = Tenant::whereHas('users', fn ($q) => $q->where('user_id', $user->id))->first();

        $asaas      = app(AsaasService::class);
        $customerId = $asaas->criarOuBuscarCliente($user, $tenant);

        $subscription = $asaas->criarAssinatura($customerId, $request->plano);

        $tenant->update([
            'asaas_subscription_id' => $subscription['id'] ?? null,
            'plano'                 => $request->plano,
        ]);

        $linkPagamento = $asaas->gerarLinkCheckout($subscription['id'] ?? '');

        if ($linkPagamento) {
            return redirect($linkPagamento);
        }

        return redirect()->route('onboarding.sucesso');
    }

    public function sucesso(): Response
    {
        return Inertia::render('Onboarding/Sucesso', [
            'user' => auth()->user(),
        ]);
    }

    public function pularPagamento(): RedirectResponse
    {
        return redirect()->route('tenant.dashboard');
    }
}
