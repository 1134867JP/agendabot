<?php

namespace App\Http\Controllers;

use App\Models\HorarioFuncionamento;
use App\Models\Recurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HorarioController extends Controller
{
    public function store(Request $request, Recurso $recurso): RedirectResponse
    {
        abort_unless($recurso->tenant_id === app('tenant')->id, 403);

        $data = $request->validate([
            'dia_semana' => ['required', 'integer', 'min:0', 'max:6'],
            'abertura'   => ['required', 'date_format:H:i'],
            'fechamento' => ['required', 'date_format:H:i', 'after:abertura'],
        ]);

        $recurso->horariosFuncionamento()->updateOrCreate(
            ['dia_semana' => $data['dia_semana']],
            ['abertura' => $data['abertura'], 'fechamento' => $data['fechamento']],
        );

        return back()->with('sucesso', 'Horário salvo.');
    }

    public function update(Request $request, HorarioFuncionamento $horario): RedirectResponse
    {
        abort_unless($horario->recurso->tenant_id === app('tenant')->id, 403);

        $data = $request->validate([
            'abertura'   => ['required', 'date_format:H:i'],
            'fechamento' => ['required', 'date_format:H:i', 'after:abertura'],
        ]);

        $horario->update($data);

        return back()->with('sucesso', 'Horário atualizado.');
    }

    public function destroy(HorarioFuncionamento $horario): RedirectResponse
    {
        abort_unless($horario->recurso->tenant_id === app('tenant')->id, 403);

        $horario->delete();

        return back()->with('sucesso', 'Horário removido.');
    }
}
