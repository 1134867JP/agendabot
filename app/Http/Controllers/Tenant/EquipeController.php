<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Profissional;
use App\Models\User;
use App\Services\ConviteEquipeService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EquipeController extends Controller
{
    private function apenasAdmin(): void
    {
        if (auth()->user()->is_super_admin) {
            return;
        }
        $tenant = app('tenant');
        $papel = $tenant->users()->where('user_id', auth()->id())->value('papel');
        abort_if($papel !== 'admin', 403);
    }

    public function index(): Response
    {
        $this->apenasAdmin();

        $tenant = app('tenant');
        $usuarios = $tenant->users()
            ->select('users.id', 'users.name', 'users.email', 'users.email_verified_at', 'tenant_users.papel', 'tenant_users.ativo', 'tenant_users.created_at as membro_desde')
            ->orderBy('tenant_users.created_at')
            ->get();

        $profissionaisPorUsuario = $tenant->profissionais()
            ->whereNotNull('user_id')
            ->pluck('nome', 'user_id');

        $usuarios->each(function (User $usuario) use ($profissionaisPorUsuario): void {
            $usuario->setAttribute('profissional_nome', $profissionaisPorUsuario[$usuario->id] ?? null);
            // Sem e-mail confirmado a pessoa ainda não aceitou o convite (ou não confirmou o cadastro).
            $usuario->setAttribute('convite_pendente', $usuario->email_verified_at === null);
            $usuario->makeHidden('email_verified_at');
        });

        return Inertia::render('Tenant/Equipe', [
            'usuarios' => $usuarios,
            'meu_id' => auth()->id(),
            'profissionais_disponiveis' => $tenant->profissionais()
                ->where('ativo', true)
                ->whereNull('user_id')
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'email_configurado' => ConviteEquipeService::emailConfigurado(),
        ]);
    }

    public function store(Request $request, ConviteEquipeService $convites): RedirectResponse
    {
        $this->apenasAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            // Sem senha, a pessoa recebe um convite por e-mail para criar a própria.
            'password' => ['nullable', 'min:8'],
            'papel' => ['required', 'in:admin,recepcionista,profissional'],
            'profissional_id' => [
                'nullable',
                'required_if:papel,profissional',
                'integer',
                Rule::exists('profissionais', 'id')->where('tenant_id', app('tenant')->id),
            ],
        ]);

        $tenant = app('tenant');
        $porConvite = blank($validated['password'] ?? null);

        $user = DB::transaction(function () use ($tenant, $validated, $porConvite): User {
            $profissional = null;
            if ($validated['papel'] === 'profissional') {
                $profissional = Profissional::where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->findOrFail($validated['profissional_id']);

                abort_if($profissional->user_id, 422, 'Este profissional já possui um login vinculado.');
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($porConvite ? Str::password(40) : $validated['password']),
            ]);

            $tenant->users()->attach($user->id, ['papel' => $validated['papel'], 'ativo' => true]);
            $profissional?->update(['user_id' => $user->id]);

            return $user;
        });

        if ($porConvite) {
            $convites->enviar($user, $tenant->nome, auth()->user()->name);

            return back()->with('success', "Convite enviado para {$user->email}. O link para criar a senha vale 7 dias.");
        }

        event(new Registered($user));

        return back()->with('success', $validated['papel'] === 'profissional'
            ? 'Login do profissional criado e vinculado.'
            : 'Usuário adicionado à equipe.');
    }

    public function reenviarConvite(User $user, ConviteEquipeService $convites): RedirectResponse
    {
        $this->apenasAdmin();

        $tenant = app('tenant');
        abort_if(! $tenant->users()->where('user_id', $user->id)->exists(), 403);
        abort_if($user->hasVerifiedEmail(), 422, 'Esta pessoa já criou a senha e acessa normalmente.');

        $convites->enviar($user, $tenant->nome, auth()->user()->name);

        return back()->with('success', "Novo convite enviado para {$user->email}. O link anterior deixou de valer.");
    }

    public function toggleAtivo(User $user): RedirectResponse
    {
        $this->apenasAdmin();

        $tenant = app('tenant');
        abort_if($user->id === auth()->id(), 422, 'Você não pode desativar o próprio acesso.');

        $membro = $tenant->users()->where('user_id', $user->id)->firstOrFail();
        $estaAtivo = (bool) $membro->pivot->ativo;

        if ($estaAtivo && $membro->pivot->papel === 'admin') {
            $adminsAtivos = $tenant->users()
                ->wherePivot('papel', 'admin')
                ->wherePivot('ativo', true)
                ->count();
            abort_if($adminsAtivos <= 1, 422, 'Mantenha ao menos um administrador ativo.');
        }

        $tenant->users()->updateExistingPivot($user->id, ['ativo' => ! $estaAtivo]);

        return back()->with('success', $estaAtivo ? 'Acesso do atendente bloqueado.' : 'Acesso do atendente reativado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->apenasAdmin();

        $tenant = app('tenant');

        abort_if($user->id === auth()->id(), 422, 'Você não pode remover a si mesmo.');
        abort_if(! $tenant->users()->where('user_id', $user->id)->exists(), 403);

        $tenant->profissionais()->where('user_id', $user->id)->update(['user_id' => null]);
        $tenant->users()->detach($user->id);

        // Remove a conta se não pertence a mais nenhum tenant
        $contaExcluida = $user->tenants()->count() === 0;

        if ($contaExcluida) {
            $user->delete();
        }

        return back()->with('success', $contaExcluida
            ? 'Membro e login excluídos.'
            : 'Membro removido deste estabelecimento.');
    }
}
