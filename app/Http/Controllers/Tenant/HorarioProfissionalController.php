<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Profissional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HorarioProfissionalController extends Controller
{
    public function sync(Request $request, Profissional $profissional): RedirectResponse
    {
        abort_if((int) $profissional->tenant_id !== (int) app('tenant')->id, 403);

        $request->validate([
            'horarios' => 'required|array',
            'horarios.*.dia_semana' => 'required|integer|between:0,6',
            'horarios.*.hora_inicio' => 'required|date_format:H:i',
            'horarios.*.hora_fim' => 'required|date_format:H:i',
            'horarios.*.intervalo_inicio' => 'nullable|date_format:H:i|required_with:horarios.*.intervalo_fim',
            'horarios.*.intervalo_fim' => 'nullable|date_format:H:i|required_with:horarios.*.intervalo_inicio',
            'horarios.*.duracao_slot' => 'integer|min:15|max:240',
            'horarios.*.ativo' => 'boolean',
        ]);

        foreach ($request->horarios as $i => $h) {
            if (! ($h['ativo'] ?? false)) {
                continue;
            }
            if ($h['hora_fim'] <= $h['hora_inicio']) {
                throw ValidationException::withMessages([
                    "horarios.{$i}.hora_fim" => 'O fim do expediente precisa ser depois do início.',
                ]);
            }
            $intervaloInicio = $h['intervalo_inicio'] ?? null;
            $intervaloFim = $h['intervalo_fim'] ?? null;
            if ($intervaloInicio && $intervaloFim && (
                $intervaloFim <= $intervaloInicio
                || $intervaloInicio <= $h['hora_inicio']
                || $intervaloFim >= $h['hora_fim']
            )) {
                throw ValidationException::withMessages([
                    "horarios.{$i}.intervalo_inicio" => 'O intervalo precisa ficar dentro do expediente, com fim depois do início.',
                ]);
            }
        }

        $profissional->horarios()->delete();

        collect($request->horarios)
            ->where('ativo', true)
            ->each(fn ($h) => $profissional->horarios()->create([
                ...$h,
                'intervalo_inicio' => ($h['intervalo_inicio'] ?? null) ?: null,
                'intervalo_fim' => ($h['intervalo_fim'] ?? null) ?: null,
            ]));

        return back()->with('success', 'Horários salvos.');
    }
}
