<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            ->select('users.id', 'users.name', 'users.email', 'tenant_users.papel', 'tenant_users.ativo', 'tenant_users.created_at as membro_desde')
            ->orderBy('tenant_users.created_at')
            ->get();

        return Inertia::render('Tenant/Equipe', [
            'usuarios' => $usuarios,
            'meu_id' => auth()->id(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->apenasAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
            'papel' => ['required', 'in:admin,operador'],
        ]);

        $tenant = app('tenant');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $tenant->users()->attach($user->id, ['papel' => $validated['papel'], 'ativo' => true]);
        event(new Registered($user));

        return back()->with('success', 'Usuário adicionado à equipe.');
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

        $tenant->users()->detach($user->id);

        // Remove a conta se não pertence a mais nenhum tenant
        $contaExcluida = $user->tenants()->count() === 0;

        if ($contaExcluida) {
            $user->delete();
        }

        return back()->with('success', $contaExcluida
            ? 'Atendente e login excluídos.'
            : 'Atendente removido deste estabelecimento.');
    }
}
