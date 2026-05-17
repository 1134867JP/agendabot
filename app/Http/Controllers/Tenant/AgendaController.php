<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/Agenda', [
            'tenant'   => $tenant,
            'recursos' => $tenant->recursos()->where('ativo', true)->get(),
        ]);
    }

    public function disponibilidade(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        $request->validate([
            'recurso_id'  => ['required', 'exists:recursos,id'],
            'data_inicio' => ['required', 'date'],
            'data_fim'    => ['required', 'date'],
        ]);

        $agendamentos = Agendamento::where('tenant_id', $tenant->id)
            ->where('recurso_id', $request->recurso_id)
            ->where('status', '!=', 'cancelado')
            ->whereBetween('inicio', [$request->data_inicio, $request->data_fim])
            ->with('recurso')
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'title'       => $a->cliente_nome,
                'start'       => $a->inicio,
                'end'         => $a->fim,
                'telefone'    => $a->cliente_telefone,
                'status'      => $a->status,
                'valor_total' => $a->valor_total,
                'origem'      => $a->origem,
            ]);

        return response()->json($agendamentos);
    }
}
