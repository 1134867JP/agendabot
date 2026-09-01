<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Support\TenantAccess;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $query = TenantAccess::scopeClientes($tenant->clientes(), $tenant)
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

        return Inertia::render('Tenant/Clientes/Index', [
            'clientes' => $query->paginate(30)->withQueryString(),
            'filtros' => $request->only('busca', 'segmento'),
            'resumo' => [
                'total' => TenantAccess::scopeClientes($tenant->clientes(), $tenant)->where('nome', '!=', 'Cliente anonimizado')->count(),
                'recorrentes' => TenantAccess::scopeClientes($tenant->clientes(), $tenant)->where('nome', '!=', 'Cliente anonimizado')->has('agendamentos', '>=', 2)->count(),
                'sem_agendamento' => TenantAccess::scopeClientes($tenant->clientes(), $tenant)->where('nome', '!=', 'Cliente anonimizado')->doesntHave('agendamentos')->count(),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json(['clientes' => []]);
        }

        $digitos = preg_replace('/\D+/', '', $termo);

        $clientes = TenantAccess::scopeClientes($tenant->clientes(), $tenant)
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

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        abort_if(TenantAccess::profissionalId($tenant), 403);
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'telefone' => ['required', 'string', 'max:30'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $telefone = $this->normalizarTelefone($data['telefone']);
        if (! preg_match('/^55[1-9]\d{9,10}$/', $telefone)) {
            throw ValidationException::withMessages([
                'telefone' => 'Informe um telefone brasileiro válido com DDD.',
            ]);
        }

        $cliente = DB::transaction(function () use ($tenant, $data, $telefone): Cliente {
            DB::table('tenants')->where('id', $tenant->id)->lockForUpdate()->first();

            $existente = $tenant->clientes()
                ->where('telefone', $telefone)
                ->where('nome', '!=', 'Cliente anonimizado')
                ->first();

            if ($existente) {
                throw ValidationException::withMessages([
                    'telefone' => "Este telefone já pertence a {$existente->nome}.",
                ]);
            }

            return $tenant->clientes()->create([
                'nome' => trim($data['nome']),
                'telefone' => $telefone,
                'observacoes' => filled($data['observacoes'] ?? null) ? trim($data['observacoes']) : null,
            ]);
        });

        return redirect()
            ->route('tenant.clientes.show', $cliente)
            ->with('success', 'Cliente cadastrado. Agora você pode agendar ou iniciar uma conversa.');
    }

    public function show(Cliente $cliente): Response
    {
        TenantAccess::assertCliente($cliente, app('tenant'));

        return Inertia::render('Tenant/Clientes/Show', [
            'cliente' => $cliente,
            'agendamentos' => TenantAccess::scopeAgendamentos($cliente->agendamentos(), app('tenant'))
                ->with('profissional', 'servico')
                ->orderByDesc('data_hora')
                ->limit(30)
                ->get(),
            'conversas' => TenantAccess::scopeConversas($cliente->conversas(), app('tenant'))
                ->orderByDesc('ultima_mensagem_em')
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        TenantAccess::assertCliente($cliente, app('tenant'));

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $cliente->update($data);

        return back()->with('success', 'Dados do cliente atualizados.');
    }

    public function destroy($cliente): RedirectResponse
    {
        $tenant = app('tenant');

        // Resolve o cliente explicitamente dentro do tenant. Além de evitar que a
        // exclusão dependa do binding implícito, a consulta já nasce tenant-scoped.
        $cliente = TenantAccess::scopeClientes($tenant->clientes(), $tenant)
            ->whereKey($cliente)
            ->where('nome', '!=', 'Cliente anonimizado')
            ->firstOrFail();

        DB::transaction(fn () => $this->anonimizarCliente($cliente));

        return redirect()
            ->route('tenant.clientes.index')
            ->with('success', 'Cliente anonimizado. O histórico operacional foi preservado.');
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'cliente_ids' => ['required', 'array', 'min:1', 'max:100'],
            'cliente_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('clientes', 'id')->where('tenant_id', $tenant->id),
            ],
        ]);

        $quantidade = DB::transaction(function () use ($tenant, $data): int {
            $clientes = TenantAccess::scopeClientes($tenant->clientes(), $tenant)
                ->whereIn('id', $data['cliente_ids'])
                ->where('nome', '!=', 'Cliente anonimizado')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $clientes->each(fn (Cliente $cliente) => $this->anonimizarCliente($cliente));

            return $clientes->count();
        });

        return back()->with(
            'success',
            "{$quantidade} cliente".($quantidade === 1 ? '' : 's').' anonimizado'.($quantidade === 1 ? '' : 's').'. O histórico operacional foi preservado.'
        );
    }

    private function anonimizarCliente(Cliente $cliente): void
    {
        $cliente->conversas()
            ->select(['id'])
            ->orderBy('id')
            ->get()
            ->each(function ($conversa) use ($cliente): void {
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
    }

    private function normalizarTelefone(string $telefone): string
    {
        $digitos = preg_replace('/\D+/', '', $telefone);

        return str_starts_with($digitos, '55') && strlen($digitos) >= 12
            ? $digitos
            : '55'.$digitos;
    }

    public function export(Cliente $cliente): JsonResponse
    {
        TenantAccess::assertCliente($cliente, app('tenant'));

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'cliente' => $cliente,
            'agendamentos' => $cliente->agendamentos()->get(),
            'conversas' => $cliente->conversas()->with('mensagens')->get(),
        ])->header('Content-Disposition', "attachment; filename=cliente-{$cliente->id}.json");
    }
}
