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
            'webhook_token'              => Str::random(32),
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
            'plano' => 'required|in:starter,pro,business',
        ]);

        $user   = auth()->user();
        $tenant = Tenant::whereHas('users', fn ($q) => $q->where('user_id', $user->id))->firstOrFail();
        $taxa = config("plans.{$request->plano}.taxa_agendamento_bot", 0.40);
        $tenant->update(['plano' => $request->plano, 'taxa_agendamento_bot' => $taxa]);

        try {
            $asaas        = app(AsaasService::class);
            $customerId   = $asaas->criarOuBuscarCliente($user, $tenant);
            $subscription = $asaas->criarAssinatura($customerId, $request->plano);

            $tenant->update(['asaas_subscription_id' => $subscription['id'] ?? null]);

            $link = $asaas->gerarLinkCheckout($subscription['id'] ?? '');
            if ($link) {
                return redirect($link);
            }
        } catch (\Throwable) {
            // Asaas indisponível — continua o onboarding normalmente
        }

        return redirect()->route('onboarding.step3');
    }

    public function step3(): Response
    {
        $tenant = Tenant::whereHas('users', fn ($q) => $q->where('user_id', auth()->id()))->firstOrFail();

        return Inertia::render('Onboarding/Step3', [
            'tenant' => [
                'nome'               => $tenant->nome,
                'tipo_servico'       => $tenant->tipo_servico,
                'nome_agente'        => $tenant->nome_agente,
                'tom_voz'            => $tenant->tom_voz,
                'instrucoes_extras'  => $tenant->instrucoes_extras,
            ],
        ]);
    }

    public function step3Store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bot_nome'     => 'required|string|min:2|max:80',
            'bot_saudacao' => 'required|string|min:10|max:500',
            'bot_tom'      => 'required|in:formal,semiformal,descontraido',
        ]);

        $tenant = Tenant::whereHas('users', fn ($q) => $q->where('user_id', auth()->id()))->firstOrFail();

        $tenant->update([
            'nome_agente'       => $validated['bot_nome'],
            'tom_voz'           => $validated['bot_tom'],
            'instrucoes_extras' => $validated['bot_saudacao'],
        ]);

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
        return redirect()->route('onboarding.step3');
    }
}
