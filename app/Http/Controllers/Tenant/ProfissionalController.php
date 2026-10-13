<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Profissional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfissionalController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/Profissionais/Index', [
            'profissionais' => $tenant->profissionais()->with(['horarios', 'servicos:id,nome'])->orderBy('nome')->get(),
            'servicos_disponiveis' => $tenant->servicos()->where('ativo', true)->orderBy('nome')
                ->get(['id', 'nome', 'valor_min', 'valor_max', 'duracao_minutos']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $tenantId = $tenant->id;
        $limite = $tenant->limiteProfissionais();
        if ($limite !== null && $tenant->profissionais()->where('ativo', true)->count() >= $limite) {
            throw ValidationException::withMessages([
                'nome' => "Seu plano permite até {$limite} profissionais ativos. Faça upgrade para adicionar outro.",
            ]);
        }

        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'especialidades' => 'nullable|array',
            'especialidades.*' => 'string|max:100',
            'ativo' => 'boolean',
            'servico_ids' => 'nullable|array',
            'servico_ids.*' => ['integer', Rule::exists('servicos', 'id')->where('tenant_id', $tenantId)],
        ]);
        $servicoIds = $data['servico_ids'] ?? [];
        $profissional = $tenant->profissionais()->create(Arr::except($data, ['servico_ids']));
        $profissional->servicos()->sync($servicoIds);

        return back()->with('success', 'Profissional criado.');
    }

    public function update(Request $request, Profissional $profissional): RedirectResponse
    {
        $tenant = app('tenant');
        abort_if((int) $profissional->tenant_id !== (int) $tenant->id, 403);
        $tenantId = $tenant->id;
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'especialidades' => 'nullable|array',
            'especialidades.*' => 'string|max:100',
            'ativo' => 'boolean',
            'servico_ids' => 'nullable|array',
            'servico_ids.*' => ['integer', Rule::exists('servicos', 'id')->where('tenant_id', $tenantId)],
        ]);
        $limite = $tenant->limiteProfissionais();
        $reativando = ! $profissional->ativo && ($data['ativo'] ?? false);
        if ($reativando && $limite !== null && $tenant->profissionais()->where('ativo', true)->count() >= $limite) {
            throw ValidationException::withMessages([
                'nome' => "Seu plano permite até {$limite} profissionais ativos. Faça upgrade para reativar este profissional.",
            ]);
        }

        $profissional->update(Arr::except($data, ['servico_ids']));
        // Editar nome ou ativar/desativar não mexe nos serviços: só sincroniza
        // quando a lista vem explicitamente no pedido.
        if (array_key_exists('servico_ids', $data)) {
            $profissional->servicos()->sync($data['servico_ids'] ?? []);
        }

        return back()->with('success', 'Profissional atualizado.');
    }

    /**
     * Serviços que o profissional faz, com preço e duração próprios opcionais
     * (vazio = usa o padrão do serviço).
     */
    public function sincronizarServicos(Request $request, Profissional $profissional): RedirectResponse
    {
        $tenantId = app('tenant')->id;
        abort_if((int) $profissional->tenant_id !== (int) $tenantId, 403);

        $data = $request->validate([
            'servicos' => 'present|array',
            'servicos.*.servico_id' => ['required', 'integer', 'distinct', Rule::exists('servicos', 'id')->where('tenant_id', $tenantId)],
            'servicos.*.valor' => 'nullable|numeric|min:0|max:99999',
            'servicos.*.duracao_minutos' => 'nullable|integer|min:5|max:480',
        ], [
            'servicos.*.duracao_minutos.min' => 'A duração precisa ter pelo menos 5 minutos.',
        ]);

        $profissional->servicos()->sync(collect($data['servicos'])->mapWithKeys(fn ($item) => [
            (int) $item['servico_id'] => [
                'valor' => $item['valor'] ?? null,
                'duracao_minutos' => $item['duracao_minutos'] ?? null,
            ],
        ])->all());

        return back()->with('success', "Serviços de {$profissional->nome} salvos.");
    }

    public function destroy(Profissional $profissional): RedirectResponse
    {
        abort_if((int) $profissional->tenant_id !== (int) app('tenant')->id, 403);
        $profissional->update(['ativo' => false]);

        return back()->with('success', 'Profissional removido.');
    }
}
