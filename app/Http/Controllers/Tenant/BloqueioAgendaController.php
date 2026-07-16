<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\BloqueioAgenda;
use App\Models\Profissional;
use App\Models\Recurso;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BloqueioAgendaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'profissional_id' => ['nullable', Rule::exists('profissionais', 'id')->where('tenant_id', $tenant->id)],
            'recurso_id' => ['nullable', Rule::exists('recursos', 'id')->where('tenant_id', $tenant->id)],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after:inicio'],
            'motivo' => ['nullable', 'string', 'max:120'],
        ]);

        if (empty($data['profissional_id']) === empty($data['recurso_id'])) {
            throw ValidationException::withMessages([
                'profissional_id' => 'Selecione exatamente um profissional ou recurso.',
            ]);
        }

        $inicio = Carbon::parse($data['inicio']);
        $fim = Carbon::parse($data['fim']);

        DB::transaction(function () use ($tenant, $data, $inicio, $fim): void {
            $profissionalId = isset($data['profissional_id']) ? (int) $data['profissional_id'] : null;
            $recursoId = isset($data['recurso_id']) ? (int) $data['recurso_id'] : null;

            if ($profissionalId) {
                Profissional::where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($profissionalId);
                $temAgendamento = Agendamento::where('profissional_id', $profissionalId)
                    ->whereNotIn('status', ['cancelado'])
                    ->where('data_hora', '<', $fim)
                    ->whereRaw("(data_hora + (duracao_minutos * INTERVAL '1 minute')) > ?", [$inicio])
                    ->exists();
            } else {
                Recurso::where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($recursoId);
                $temAgendamento = Agendamento::where('recurso_id', $recursoId)
                    ->where('status', '!=', 'cancelado')
                    ->where('inicio', '<', $fim)
                    ->where('fim', '>', $inicio)
                    ->exists();
            }

            if ($temAgendamento) {
                throw ValidationException::withMessages([
                    'inicio' => 'Já existe uma reserva nesse período. Cancele ou reagende a reserva antes de bloquear.',
                ]);
            }

            $temBloqueio = BloqueioAgenda::where('tenant_id', $tenant->id)
                ->when($profissionalId, fn ($query) => $query->where('profissional_id', $profissionalId))
                ->when($recursoId, fn ($query) => $query->where('recurso_id', $recursoId))
                ->where('inicio', '<', $fim)
                ->where('fim', '>', $inicio)
                ->exists();

            if ($temBloqueio) {
                throw ValidationException::withMessages(['inicio' => 'Esse período já está bloqueado.']);
            }

            BloqueioAgenda::create([
                'tenant_id' => $tenant->id,
                'profissional_id' => $profissionalId,
                'recurso_id' => $recursoId,
                'inicio' => $inicio,
                'fim' => $fim,
                'motivo' => trim((string) ($data['motivo'] ?? '')) ?: null,
            ]);
        });

        return back()->with('success', 'Horário bloqueado.');
    }

    public function destroy(BloqueioAgenda $bloqueio): RedirectResponse
    {
        abort_if((int) $bloqueio->tenant_id !== (int) app('tenant')->id, 403);
        $bloqueio->delete();

        return back()->with('success', 'Bloqueio removido.');
    }
}
