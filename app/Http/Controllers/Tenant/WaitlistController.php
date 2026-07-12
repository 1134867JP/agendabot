<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaitlistController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'cliente_nome' => ['required', 'string', 'max:255'],
            'cliente_telefone' => ['required', 'string', 'max:40'],
            'profissional_id' => ['nullable', Rule::exists('profissionais', 'id')->where('tenant_id', $tenant->id)],
            'servico_id' => ['nullable', Rule::exists('servicos', 'id')->where('tenant_id', $tenant->id)],
            'data_preferida' => ['nullable', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
        ]);

        WaitlistEntry::create(['tenant_id' => $tenant->id, ...$data]);

        return back()->with('success', 'Cliente adicionado à lista de espera.');
    }

    public function destroy(WaitlistEntry $waitlistEntry): RedirectResponse
    {
        abort_unless($waitlistEntry->tenant_id === app('tenant')->id, 403);
        $waitlistEntry->delete();

        return back()->with('success', 'Entrada removida da lista de espera.');
    }
}
