<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\Step1Request;
use App\Http\Requests\Onboarding\Step3Request;
use App\Jobs\CreateEvolutionInstanceJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingPresetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function step1Store(Step1Request $request): RedirectResponse
    {
        $validated = $request->validated();

        $slugInterno = Str::slug($validated['nome_estabelecimento']).'-'.Str::random(6);

        [$user, $tenant] = DB::transaction(function () use ($validated, $slugInterno): array {
            $user = User::create([
                'name' => $validated['nome_usuario'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['senha']),
                'telefone' => $validated['telefone'],
            ]);

            $tenant = Tenant::create([
                'nome' => $validated['nome_estabelecimento'],
                'slug' => $slugInterno,
                'tipo_servico' => $validated['tipo_servico'],
                'tipo_servico_personalizado' => $validated['tipo_servico_personalizado'] ?? null,
                'telefone_whatsapp' => $validated['telefone'],
                'evolution_instance' => $slugInterno,
                'webhook_token' => Str::random(64),
                'plano' => 'starter',
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays((int) env('TRIAL_DAYS', 14)),
                'ativo' => true,
            ]);

            $tenant->users()->attach($user->id, ['papel' => 'admin']);

            return [$user, $tenant];
        });

        CreateEvolutionInstanceJob::dispatch($tenant)->onQueue('sync');

        Auth::login($user);
        $request->session()->regenerate();
        session(['tenant_id' => $tenant->id]);

        return redirect()->route('onboarding.step3');
    }

    public function step2(): Response
    {
        return Inertia::render('Onboarding/Step2', [
            'planos' => config('plans'),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plano' => 'required|in:starter,pro,business',
        ]);

        $tenant = Tenant::whereHas('users', fn ($q) => $q->where('user_id', auth()->id()))->first();

        if (! $tenant) {
            return redirect()->route('dashboard')->with('erro', 'Complete o cadastro do seu estabelecimento antes de escolher um plano.');
        }

        // O trial não exige cartão. O método de pagamento será escolhido apenas
        // quando o cliente decidir ativar ou renovar a assinatura.
        $tenant->update([
            'plano' => $validated['plano'],
            'taxa_agendamento_bot' => 0,
        ]);

        return redirect()->route('onboarding.step3');
    }

    public function step3(OnboardingPresetService $presets): Response|RedirectResponse
    {
        $tenant = Tenant::whereHas('users', fn ($q) => $q->where('user_id', auth()->id()))->first();

        if (! $tenant) {
            return redirect()->route('dashboard')->with('erro', 'Complete o cadastro do seu estabelecimento antes de continuar.');
        }

        return Inertia::render('Onboarding/Step3', [
            'tenant' => [
                'nome' => $tenant->nome,
                'tipo_servico' => $tenant->tipo_servico,
                'nome_agente' => $tenant->nome_agente,
                'tom_voz' => $tenant->tom_voz,
                'bot_saudacao' => $tenant->bot_saudacao,
            ],
            'defaults' => $presets->defaults($tenant, auth()->user()->name),
        ]);
    }

    public function step3Store(Step3Request $request, OnboardingPresetService $presets): RedirectResponse
    {
        $validated = $request->validated();

        $tenant = Tenant::whereHas('users', fn ($q) => $q->where('user_id', auth()->id()))->first();

        if (! $tenant) {
            return redirect()->route('dashboard')->with('erro', 'Complete o cadastro do seu estabelecimento antes de continuar.');
        }

        $presets->aplicar($tenant, $validated);

        return redirect()->route('onboarding.sucesso');
    }

    public function sucesso(): Response
    {
        $tenant = Tenant::whereHas('users', fn ($q) => $q->where('user_id', auth()->id()))->first();

        return Inertia::render('Onboarding/Sucesso', [
            'user' => auth()->user(),
            'tenant' => $tenant ? $tenant->only(['nome', 'tipo_servico']) : null,
        ]);
    }

    public function pularPagamento(): RedirectResponse
    {
        return redirect()->route('onboarding.step3');
    }
}
