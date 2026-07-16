<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if ($request->segmento === 'recorrentes') {
            $query->has('agendamentos', '>=', 2);
        } elseif ($request->segmento === 'sem_agendamento') {
            $query->doesntHave('agendamentos');
        }

        return Inertia::render('Tenant/Clientes/Index', [
            'clientes' => $query->paginate(30)->withQueryString(),
            'filtros' => $request->only('busca', 'segmento'),
            'resumo' => [
                'total' => $tenant->clientes()->count(),
                'recorrentes' => $tenant->clientes()->has('agendamentos', '>=', 2)->count(),
                'sem_agendamento' => $tenant->clientes()->doesntHave('agendamentos')->count(),
            ],
        ]);
    }

    public function show(Cliente $cliente): Response
    {
        abort_if((int) $cliente->tenant_id !== (int) app('tenant')->id, 403);

        return Inertia::render('Tenant/Clientes/Show', [
            'cliente' => $cliente,
            'agendamentos' => $cliente->agendamentos()
                ->with('profissional', 'servico')
                ->orderByDesc('data_hora')
                ->limit(30)
                ->get(),
            'conversas' => $cliente->conversas()
                ->orderByDesc('ultima_mensagem_em')
                ->limit(20)
                ->get(),
        ]);
    }


    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        abort_if((int) $cliente->tenant_id !== (int) app('tenant')->id, 403);

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $cliente->update($data);

        return back()->with('success', 'Dados do cliente atualizados.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        abort_if((int) $cliente->tenant_id !== (int) app('tenant')->id, 403);

        DB::transaction(function () use ($cliente): void {
            $cliente->conversas()->each(function ($conversa): void {
                $conversa->mensagens()->delete();
                $conversa->delete();
            });
            $cliente->agendamentos()->update([
                'cliente_nome' => 'Cliente anonimizado',
                'cliente_telefone' => 'anonimizado',
                'observacoes' => null,
            ]);
            $cliente->update([
                'nome' => 'Cliente anonimizado',
                'telefone' => "anonimizado-{$cliente->id}",
                'cpf' => null,
                'data_nascimento' => null,
                'observacoes' => null,
            ]);
        });

        return redirect()->route('tenant.clientes.index');
    }

    public function export(Cliente $cliente): JsonResponse
    {
        abort_if((int) $cliente->tenant_id !== (int) app('tenant')->id, 403);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'cliente' => $cliente,
            'agendamentos' => $cliente->agendamentos()->get(),
            'conversas' => $cliente->conversas()->with('mensagens')->get(),
        ])->header('Content-Disposition', "attachment; filename=cliente-{$cliente->id}.json");
    }
}
