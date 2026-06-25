<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $query = $tenant->clientes()->withCount('agendamentos')->orderBy('nome');

        if ($busca = $request->busca) {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'ilike', "%{$busca}%")
                  ->orWhere('telefone', 'like', "%{$busca}%");
            });
        }

        return Inertia::render('Tenant/Clientes/Index', [
            'clientes' => $query->paginate(30)->withQueryString(),
            'filtros'  => $request->only('busca'),
        ]);
    }

    public function show(Cliente $cliente): Response
    {
        abort_if((int)$cliente->tenant_id !== (int)app('tenant')->id, 403);

        return Inertia::render('Tenant/Clientes/Show', [
            'cliente'      => $cliente,
            'agendamentos' => $cliente->agendamentos()
                ->with('profissional', 'servico')
                ->orderByDesc('data_hora')
                ->limit(30)
                ->get(),
            'conversas'    => $cliente->conversas()
                ->orderByDesc('ultima_mensagem_em')
                ->limit(20)
                ->get(),
        ]);
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        abort_if((int)$cliente->tenant_id !== (int)app('tenant')->id, 403);

        $cliente->conversas()->delete();
        $cliente->agendamentos()->delete();
        $cliente->delete();

        return redirect()->route('tenant.clientes.index');
    }
}
