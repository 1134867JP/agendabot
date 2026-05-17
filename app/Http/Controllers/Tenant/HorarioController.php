<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Recurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HorarioController extends Controller
{
    public function sync(Request $request, Recurso $recurso): RedirectResponse
    {
        abort_unless($recurso->tenant_id === app('tenant')->id, 403);

        $request->validate([
            'horarios'                => ['required', 'array'],
            'horarios.*.dia_semana'   => ['required', 'integer', 'between:0,6'],
            'horarios.*.abertura'     => ['required', 'date_format:H:i'],
            'horarios.*.fechamento'   => ['required', 'date_format:H:i'],
            'horarios.*.ativo'        => ['boolean'],
        ]);

        $recurso->horariosFuncionamento()->delete();

        collect($request->horarios)
            ->filter(fn ($h) => ! empty($h['ativo']))
            ->each(fn ($h) => $recurso->horariosFuncionamento()->create([
                'dia_semana' => $h['dia_semana'],
                'abertura'   => $h['abertura'],
                'fechamento' => $h['fechamento'],
            ]));

        return back()->with('success', 'Horários salvos.');
    }
}
