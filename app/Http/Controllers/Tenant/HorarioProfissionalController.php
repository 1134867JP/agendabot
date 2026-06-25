<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Profissional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HorarioProfissionalController extends Controller
{
    public function sync(Request $request, Profissional $profissional): RedirectResponse
    {
        abort_if((int)$profissional->tenant_id !== (int)app('tenant')->id, 403);

        $request->validate([
            'horarios'                => 'required|array',
            'horarios.*.dia_semana'   => 'required|integer|between:0,6',
            'horarios.*.hora_inicio'  => 'required|date_format:H:i',
            'horarios.*.hora_fim'     => 'required|date_format:H:i',
            'horarios.*.duracao_slot' => 'integer|min:15|max:240',
            'horarios.*.ativo'        => 'boolean',
        ]);

        $profissional->horarios()->delete();

        collect($request->horarios)
            ->where('ativo', true)
            ->each(fn ($h) => $profissional->horarios()->create($h));

        return back()->with('success', 'Horários salvos.');
    }
}
