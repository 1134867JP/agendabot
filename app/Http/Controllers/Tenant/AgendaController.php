<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/Agenda', [
            'tenant'        => $tenant,
            'recursos'      => $tenant->recursos()->where('ativo', true)->get(),
            'profissionais' => $tenant->profissionais()->where('ativo', true)->get(['id', 'nome']),
        ]);
    }

    public function disponibilidade(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        $tenantId = $tenant->id;
        $request->validate([
            'recurso_id'      => ['nullable', Rule::exists('recursos', 'id')->where('tenant_id', $tenantId)],
            'profissional_id' => ['nullable', Rule::exists('profissionais', 'id')->where('tenant_id', $tenantId)],
            'todos_profissionais' => ['nullable', 'boolean'],
            'data_inicio'     => ['required', 'date'],
            'data_fim'        => ['required', 'date'],
        ]);

        $query = Agendamento::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'cancelado')
            ->with('profissional:id,nome');

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

        return response()->json(
            $query->get()->map(function ($a) use ($tz) {
                $inicio = $a->inicio ?? $a->data_hora;
                $fimRaw = $a->fim ?? ($a->data_hora
                    ? Carbon::parse($a->data_hora)->addMinutes($a->duracao_minutos ?? 30)
                    : null);

                $fmtSP = fn ($dt) => $dt
                    ? Carbon::parse($dt)->setTimezone($tz)->format('Y-m-d\TH:i:s')
                    : null;

                return [
                    'id'          => $a->id,
                    'title'       => $a->cliente_nome,
                    'start'       => $fmtSP($inicio),
                    'end'         => $fmtSP($fimRaw),
                    'telefone'    => $a->cliente_telefone,
                    'status'      => in_array($a->status, ['confirmado', 'agendado']) ? 'confirmado' : $a->status,
                    'valor_total' => $a->valor_total,
                    'origem'      => $a->origem ?? 'manual',
                    'profissional_id' => $a->profissional_id,
                    'profissional_nome' => $a->profissional?->nome,
                ];
            })
        );
    }
}
