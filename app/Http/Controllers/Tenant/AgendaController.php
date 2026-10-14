<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use App\Support\TenantAccess;
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
                    'personalizado_por_profissional' => AgendamentoController::personalizacoes($servico),
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
            ->with(['profissional:id,nome', 'servico:id,nome', 'itensServico:id,agendamento_id,nome,ordem']);

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

        $tz = new \DateTimeZone($tenant->resolvedTimezone());

        $agendamentos = $query->get()->map(function ($a) use ($tz) {
            $inicio = $a->inicio ?? $a->data_hora;
            $fimRaw = $a->fim ?? ($a->data_hora
                ? Carbon::parse($a->data_hora)->addMinutes($a->duracao_minutos ?? 30)
                : null);

            $fmtSP = fn ($dt) => $dt
                ? Carbon::parse($dt)->setTimezone($tz)->format('Y-m-d\TH:i:sP')
                : null;

            return [
                'tipo' => 'agendamento',
                'id' => $a->id,
                'title' => $a->cliente_nome,
                'start' => $fmtSP($inicio),
                'end' => $fmtSP($fimRaw),
                'telefone' => $a->cliente_telefone,
                'status' => in_array($a->status, ['confirmado', 'agendado']) ? 'confirmado' : $a->status,
                'no_show' => (bool) $a->no_show,
                'valor_total' => $a->valor_total,
                'origem' => $a->origem ?? 'manual',
                'profissional_id' => $a->profissional_id,
                'profissional_nome' => $a->profissional?->nome,
                'servico_nome' => $a->descricaoServicos(),
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
            $fmtSP = fn ($dt) => Carbon::parse($dt)->setTimezone($tz)->format('Y-m-d\TH:i:sP');

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

        return response()->json($agendamentos->concat($bloqueios)->concat($this->intervalos($tenant, $request, $tz))->values());
    }

    /**
     * Almoço/pausa fixa de cada profissional no período, para a agenda mostrar
     * a faixa indisponível. Não é editável aqui: muda em Profissionais → Horários.
     */
    private function intervalos($tenant, Request $request, \DateTimeZone $tz): array
    {
        // Na visão geral (todos os profissionais) as faixas de almoço poluem a
        // grade; elas aparecem ao filtrar um profissional.
        if ($request->filled('recurso_id') || ! $request->filled('profissional_id')) {
            return [];
        }

        $profissionais = $tenant->profissionais()->where('ativo', true)
            ->when($request->filled('profissional_id'), fn ($q) => $q->whereKey($request->profissional_id))
            ->with('horarios')
            ->get();

        $eventos = [];
        $dia = Carbon::parse($request->data_inicio, $tz)->startOfDay();
        $ultimo = Carbon::parse($request->data_fim, $tz)->startOfDay();
        for ($indice = 0; $dia->lte($ultimo) && $indice < 62; $dia->addDay(), $indice++) {
            foreach ($profissionais as $profissional) {
                $horario = $profissional->horarios->firstWhere('dia_semana', $dia->dayOfWeek);
                $intervalo = $horario?->intervaloNoDia($dia, $tz->getName());
                if (! $intervalo) {
                    continue;
                }
                $eventos[] = [
                    'id' => $profissional->id * 1000 + $indice,
                    'tipo' => 'intervalo',
                    'title' => 'Almoço',
                    'start' => $intervalo[0]->format('Y-m-d\TH:i:sP'),
                    'end' => $intervalo[1]->format('Y-m-d\TH:i:sP'),
                    'telefone' => '',
                    'status' => 'bloqueado',
                    'valor_total' => null,
                    'origem' => 'manual',
                    'profissional_id' => $profissional->id,
                    'profissional_nome' => $profissional->nome,
                    'servico_nome' => null,
                ];
            }
        }

        return $eventos;
    }
}
