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

class AgendaController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');
        $agendaUsaRecursos = $tenant->agendaUsaRecursos();

        return Inertia::render('Tenant/Agenda', [
            'tenant' => $tenant,
            'recursos' => $tenant->recursos()->where('ativo', true)->get(),
            'profissionais' => $agendaUsaRecursos
                ? collect()
                : $tenant->profissionais()->where('ativo', true)->get(['id', 'nome']),
            'servicos' => $agendaUsaRecursos
                ? collect()
                : $tenant->servicos()
                    ->where('ativo', true)
                    ->with('profissionais:id')
                    ->orderBy('nome')
                    ->get(['id', 'tenant_id', 'nome', 'duracao_minutos', 'valor_min', 'valor_max'])
                    ->map(fn ($servico) => [
                        'id' => $servico->id,
                        'nome' => $servico->nome,
                        'duracao_minutos' => (int) ($servico->duracao_minutos ?? 30),
                        'valor_min' => $servico->valor_min,
                        'valor_max' => $servico->valor_max,
                        'profissional_ids' => $servico->profissionais->pluck('id')->values(),
                    ]),
        ]);
    }

    public function disponibilidade(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $agendaUsaRecursos = $tenant->agendaUsaRecursos();

        $tenantId = $tenant->id;
        $request->validate([
            'recurso_id' => [Rule::requiredIf($agendaUsaRecursos), 'nullable', Rule::exists('recursos', 'id')->where('tenant_id', $tenantId)],
            'profissional_id' => [Rule::prohibitedIf($agendaUsaRecursos), 'nullable', Rule::exists('profissionais', 'id')->where('tenant_id', $tenantId)],
            'todos_profissionais' => [Rule::prohibitedIf($agendaUsaRecursos), 'nullable', 'boolean'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date'],
        ], [
            'recurso_id.required' => 'Selecione uma quadra para consultar a agenda.',
            'profissional_id.prohibited' => 'A agenda deste estabelecimento é organizada por quadras.',
            'todos_profissionais.prohibited' => 'A agenda deste estabelecimento é organizada por quadras.',
        ]);

        $query = Agendamento::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'cancelado')
            ->with(['profissional:id,nome', 'servico:id,nome']);

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
