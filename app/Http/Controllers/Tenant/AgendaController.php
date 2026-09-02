<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\TenantAccess;

class AgendaController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');
        return Inertia::render('Tenant/Agenda', [
            'tenant' => $tenant,
            'agenda' => $tenant->agendaConfig(),
            'recursos' => $tenant->agendaUsaRecursos() ? $tenant->recursos()->where('ativo', true)->get() : collect(),
            'profissionais' => ! $tenant->agendaUsaProfissionais()
                ? collect()
                : $tenant->profissionais()
                    ->where('ativo', true)
                    ->when(TenantAccess::profissionalId($tenant), fn ($query, $id) => $query->whereKey($id))
                    ->get(['id', 'nome']),
            'servicos' => $tenant->servicos()
                    ->where('ativo', true)
                    ->when(TenantAccess::profissionalId($tenant), fn ($query, $id) => $query->whereHas('profissionais', fn ($profissionais) => $profissionais->whereKey($id)))
                    ->with(['profissionais:id', 'recursos:id'])
                    ->orderBy('nome')
                    ->get(['id', 'tenant_id', 'nome', 'duracao_minutos', 'valor_min', 'valor_max', 'requer_profissional', 'requer_recurso'])
                    ->map(fn ($servico) => [
                        'id' => $servico->id,
                        'nome' => $servico->nome,
                        'duracao_minutos' => (int) ($servico->duracao_minutos ?? 30),
                        'valor_min' => $servico->valor_min,
                        'valor_max' => $servico->valor_max,
                        'profissional_ids' => $servico->profissionais->pluck('id')->values(),
                        'recurso_ids' => $servico->recursos->pluck('id')->values(),
                        'requer_profissional' => $servico->requer_profissional,
                        'requer_recurso' => $servico->requer_recurso,
                    ]),
        ]);
    }

    public function disponibilidade(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $tenantId = $tenant->id;
        $profissionalIdLogado = TenantAccess::profissionalId($tenant);
        $request->validate([
            'recurso_id' => ['nullable', Rule::exists('recursos', 'id')->where('tenant_id', $tenantId)],
            'profissional_id' => ['nullable', Rule::exists('profissionais', 'id')->where('tenant_id', $tenantId)],
            'todos_profissionais' => ['nullable', 'boolean'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date'],
        ], [
        ]);

        $query = Agendamento::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'cancelado')
            ->with(['profissional:id,nome', 'servico:id,nome']);

        if ($profissionalIdLogado) {
            abort_if(! $tenant->agendaUsaProfissionais(), 403);
            $request->merge(['profissional_id' => $profissionalIdLogado, 'todos_profissionais' => false]);
        }

        $dataFim = Carbon::parse($request->data_fim)->endOfDay()->toIso8601String();

        $query->where(function ($q) use ($request, $dataFim) {
            $q->whereBetween('inicio', [$request->data_inicio, $dataFim])
                ->orWhereBetween('data_hora', [$request->data_inicio, $dataFim]);
        });

        if ($request->filled('recurso_id')) {
            $query->where('recurso_id', $request->recurso_id);
        } elseif ($request->filled('profissional_id')) {
            $query->where('profissional_id', $request->profissional_id);
        } elseif ($request->boolean('todos_profissionais')) {
            $query->whereNotNull('profissional_id');
        } else {
            return response()->json([]);
        }

        $tz = new \DateTimeZone('America/Sao_Paulo');

        $agendamentos = $query->get()->map(function ($a) use ($tz) {
            $inicio = $a->inicio ?? $a->data_hora;
            $fimRaw = $a->fim ?? ($a->data_hora
                ? Carbon::parse($a->data_hora)->addMinutes($a->duracao_minutos ?? 30)
                : null);

            $fmtSP = fn ($dt) => $dt
                ? Carbon::parse($dt)->setTimezone($tz)->format('Y-m-d\TH:i:s')
                : null;

            return [
                'tipo' => 'agendamento',
                'id' => $a->id,
                'title' => $a->cliente_nome,
                'start' => $fmtSP($inicio),
                'end' => $fmtSP($fimRaw),
                'telefone' => $a->cliente_telefone,
                'status' => in_array($a->status, ['confirmado', 'agendado']) ? 'confirmado' : $a->status,
                'valor_total' => $a->valor_total,
                'origem' => $a->origem ?? 'manual',
                'profissional_id' => $a->profissional_id,
                'profissional_nome' => $a->profissional?->nome,
                'servico_nome' => $a->servico?->nome,
            ];
        });

        $bloqueiosQuery = BloqueioAgenda::where('tenant_id', $tenant->id)
            ->where('inicio', '<', $dataFim)
            ->where('fim', '>', $request->data_inicio)
            ->with('profissional:id,nome');

        if ($profissionalIdLogado) {
            $bloqueiosQuery->where('profissional_id', $profissionalIdLogado);
        }

        if ($request->filled('recurso_id')) {
            $bloqueiosQuery->where('recurso_id', $request->recurso_id);
        } elseif ($request->filled('profissional_id')) {
            $bloqueiosQuery->where('profissional_id', $request->profissional_id);
        } else {
            $bloqueiosQuery->whereNotNull('profissional_id');
        }

        $bloqueios = $bloqueiosQuery->get()->map(function (BloqueioAgenda $bloqueio) use ($tz) {
            $fmtSP = fn ($dt) => Carbon::parse($dt)->setTimezone($tz)->format('Y-m-d\TH:i:s');

            return [
                'id' => $bloqueio->id,
                'tipo' => 'bloqueio',
                'title' => $bloqueio->motivo ?: 'Horário bloqueado',
                'start' => $fmtSP($bloqueio->inicio),
                'end' => $fmtSP($bloqueio->fim),
                'telefone' => '',
                'status' => 'bloqueado',
                'valor_total' => null,
                'origem' => 'manual',
                'profissional_id' => $bloqueio->profissional_id,
                'profissional_nome' => $bloqueio->profissional?->nome,
                'servico_nome' => null,
            ];
        });

        return response()->json($agendamentos->concat($bloqueios)->values());
    }
}
