<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $query = $tenant->clientes()
            ->where('nome', '!=', 'Cliente anonimizado')
            ->withCount('agendamentos')
            ->orderBy('nome');

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

        $ativos = $tenant->clientes()->where('nome', '!=', 'Cliente anonimizado');

        return Inertia::render('Tenant/Clientes/Index', [
            'clientes' => $query->paginate(30)->withQueryString(),
            'filtros' => $request->only('busca', 'segmento'),
            'resumo' => [
                'total' => (clone $ativos)->count(),
                'recorrentes' => (clone $ativos)->has('agendamentos', '>=', 2)->count(),
                'sem_agendamento' => (clone $ativos)->doesntHave('agendamentos')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['required', 'string', 'max:30'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $telefone = $this->normalizarTelefone($data['telefone']);
        if (! preg_match('/^55\d{10,11}$/', $telefone)) {
            throw ValidationException::withMessages([
                'telefone' => 'Informe um telefone brasileiro válido com DDD.',
            ]);
        }

        $existente = $tenant->clientes()
            ->where('telefone', $telefone)
            ->where('nome', '!=', 'Cliente anonimizado')
            ->first();

        if ($existente) {
            throw ValidationException::withMessages([
                'telefone' => "Este telefone já pertence a {$existente->nome}.",
            ]);
        }

        $cliente = $tenant->clientes()->create([
            'nome' => trim($data['nome']),
            'telefone' => $telefone,
            'observacoes' => $data['observacoes'] ?? null,
        ]);

        return redirect()
            ->route('tenant.clientes.show', $cliente)
            ->with('success', 'Cliente cadastrado. Agora você pode agendar ou iniciar uma conversa.');
    }

    public function search(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json(['clientes' => []]);
        }

        $digitos = preg_replace('/\D+/', '', $termo);

        $clientes = $tenant->clientes()
            ->where('nome', '!=', 'Cliente anonimizado')
            ->withCount('agendamentos')
            ->where(function ($query) use ($termo, $digitos): void {
                $query->whereRaw('LOWER(nome) LIKE ?', ['%'.mb_strtolower($termo).'%']);
                if ($digitos !== '') {
                    $query->orWhere('telefone', 'like', "%{$digitos}%");
                }
            })
            ->orderByDesc('agendamentos_count')
            ->orderBy('nome')
            ->limit(8)
            ->get(['id', 'nome', 'telefone'])
            ->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'telefone' => $cliente->telefone,
                'agendamentos_count' => $cliente->agendamentos_count,
            ]);

        return response()->json(['clientes' => $clientes]);
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
            $cliente->conversas()->get()->each(function ($conversa) use ($cliente): void {
                $conversa->update([
                    'cliente_id' => null,
                    'telefone_cliente' => "anonimizado-{$cliente->id}-{$conversa->id}",
                    'status_v2' => 'encerrada',
                ]);
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

        return redirect()
            ->route('tenant.clientes.index')
            ->with('success', 'Cliente anonimizado. O histórico operacional foi preservado.');
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

    private function normalizarTelefone(string $telefone): string
    {
        $digitos = preg_replace('/\D+/', '', $telefone);

        if (str_starts_with($digitos, '55') && strlen($digitos) >= 12) {
            return $digitos;
        }

        return '55'.$digitos;
    }
}
