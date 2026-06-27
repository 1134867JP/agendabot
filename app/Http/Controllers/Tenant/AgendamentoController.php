<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\HorarioIndisponivelException;
use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Recurso;
use App\Services\AgendamentoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class AgendamentoController extends Controller
{
    public function __construct(private AgendamentoService $agendamentoService) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $query = Agendamento::where('tenant_id', $tenant->id)
            ->with(['recurso', 'profissional', 'servico'])
            ->orderByRaw('COALESCE(inicio, data_hora) DESC');

        if ($request->filled('data')) {
            $query->whereDate('inicio', $request->data);
        }
        if ($request->filled('recurso_id')) {
            $query->where('recurso_id', $request->recurso_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('busca')) {
            $query->where(function ($q) use ($request) {
                $q->where('cliente_nome', 'ilike', "%{$request->busca}%")
                  ->orWhere('cliente_telefone', 'like', "%{$request->busca}%");
            });
        }

        return Inertia::render('Tenant/Agendamentos/Index', [
            'tenant'       => $tenant,
            'agendamentos' => $query->paginate(20)->withQueryString(),
            'recursos'     => $tenant->recursos()->where('ativo', true)->get(),
            'filtros'      => $request->only(['data', 'recurso_id', 'status', 'busca']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');

        $validated = $request->validate([
            'recurso_id'        => ['nullable', 'integer', 'exists:recursos,id'],
            'profissional_id'   => ['nullable', 'integer', 'exists:profissionais,id'],
            'cliente_nome'      => ['required', 'string', 'max:255'],
            'cliente_telefone'  => ['required', 'string', 'max:20'],
            'inicio'            => ['required', 'date'],
            'fim'               => ['required', 'date', 'after:inicio'],
            'observacoes'       => ['nullable', 'string'],
            'notificar_cliente' => ['boolean'],
        ]);

        if (empty($validated['recurso_id']) && empty($validated['profissional_id'])) {
            return back()->withErrors(['profissional_id' => 'Selecione um profissional ou recurso.']);
        }

        $dados = [
            'tenant_id'        => $tenant->id,
            'cliente_nome'     => $validated['cliente_nome'],
            'cliente_telefone' => $validated['cliente_telefone'],
            'inicio'           => $validated['inicio'],
            'fim'              => $validated['fim'],
            'observacoes'      => $validated['observacoes'] ?? null,
            'origem'           => 'manual',
            'status'           => 'confirmado',
        ];

        if (!empty($validated['recurso_id'])) {
            $recurso = Recurso::where('tenant_id', $tenant->id)->findOrFail($validated['recurso_id']);
            $dados['recurso_id']  = $validated['recurso_id'];
            $dados['valor_total'] = $recurso->valor_hora
                ? $recurso->valor_hora * Carbon::parse($validated['inicio'])->diffInMinutes($validated['fim']) / 60
                : null;
        } else {
            Profissional::where('tenant_id', $tenant->id)->findOrFail($validated['profissional_id']);
            $dados['profissional_id']  = $validated['profissional_id'];
            $dados['data_hora']        = $validated['inicio'];
            $dados['duracao_minutos']  = (int) Carbon::parse($validated['inicio'])->diffInMinutes($validated['fim']);
        }

        try {
            $this->agendamentoService->criar($dados);
        } catch (HorarioIndisponivelException $e) {
            return back()->withErrors(['inicio' => $e->getMessage()]);
        }

        return back()->with('success', 'Agendamento criado com sucesso.');
    }

    public function update(Request $request, Agendamento $agendamento): RedirectResponse
    {
        abort_unless($agendamento->tenant_id === app('tenant')->id, 403);

        $validated = $request->validate([
            'cliente_nome'     => ['required', 'string', 'max:255'],
            'cliente_telefone' => ['required', 'string', 'max:20'],
            'inicio'           => ['required', 'date'],
            'fim'              => ['required', 'date', 'after:inicio'],
            'observacoes'      => ['nullable', 'string'],
        ]);

        // Verificar conflito excluindo o próprio agendamento
        $chave = $agendamento->recurso_id
            ? ['recurso_id', $agendamento->recurso_id]
            : ['profissional_id', $agendamento->profissional_id];

        $conflito = Agendamento::where($chave[0], $chave[1])
            ->where('id', '!=', $agendamento->id)
            ->where('status', '!=', 'cancelado')
            ->where('inicio', '<', $validated['fim'])
            ->where('fim', '>', $validated['inicio'])
            ->exists();

        if ($conflito) {
            return back()->withErrors(['inicio' => 'Horário não disponível.']);
        }

        $agendamento->update([
            'cliente_nome'     => $validated['cliente_nome'],
            'cliente_telefone' => $validated['cliente_telefone'],
            'inicio'           => $validated['inicio'],
            'fim'              => $validated['fim'],
            'data_hora'        => $validated['inicio'],
            'duracao_minutos'  => (int) Carbon::parse($validated['inicio'])->diffInMinutes($validated['fim']),
            'observacoes'      => $validated['observacoes'] ?? null,
        ]);

        return back()->with('success', 'Agendamento atualizado.');
    }

    public function cancelar(Agendamento $agendamento): RedirectResponse
    {
        abort_unless($agendamento->tenant_id === app('tenant')->id, 403);
        $this->agendamentoService->cancelar($agendamento);
        return back()->with('success', 'Agendamento cancelado.');
    }

    public function concluir(Agendamento $agendamento): RedirectResponse
    {
        abort_unless($agendamento->tenant_id === app('tenant')->id, 403);
        $agendamento->update(['status' => 'concluido']);
        return back()->with('success', 'Agendamento concluído.');
    }

    public function destroy(Agendamento $agendamento): RedirectResponse
    {
        abort_unless($agendamento->tenant_id === app('tenant')->id, 403);
        $agendamento->delete();
        return back()->with('success', 'Agendamento excluído.');
    }

    public function exportar(Request $request): StreamedResponse
    {
        $tenant = app('tenant');

        $query = Agendamento::where('tenant_id', $tenant->id)
            ->with(['recurso', 'profissional', 'servico'])
            ->orderBy('inicio', 'desc');

        if ($request->filled('data_inicio')) {
            $query->where(fn ($q) => $q->where('inicio', '>=', $request->data_inicio)->orWhere('data_hora', '>=', $request->data_inicio));
        }
        if ($request->filled('data_fim')) {
            $query->where(fn ($q) => $q->where('inicio', '<=', $request->data_fim)->orWhere('data_hora', '<=', $request->data_fim));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $filename = 'agendamentos-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Cliente', 'Telefone', 'Serviço / Recurso', 'Data/Hora', 'Duração (min)', 'Valor', 'Status', 'Origem'], ';');

            $query->chunk(500, function ($agendamentos) use ($handle) {
                foreach ($agendamentos as $ag) {
                    $dataHora = $ag->data_hora ?? $ag->inicio;
                    $servico = $ag->servico?->nome ?? $ag->recurso?->nome ?? '–';
                    $duracao = $ag->duracao_minutos ?? (
                        ($ag->inicio && $ag->fim)
                            ? Carbon::parse($ag->inicio)->diffInMinutes($ag->fim)
                            : '–'
                    );
                    fputcsv($handle, [
                        $ag->id,
                        $ag->cliente_nome ?? $ag->cliente?->nome ?? '–',
                        $ag->cliente_telefone ?? $ag->cliente?->telefone ?? '–',
                        $servico,
                        $dataHora ? Carbon::parse($dataHora)->format('d/m/Y H:i') : '–',
                        $duracao,
                        $ag->valor_total ? number_format($ag->valor_total, 2, ',', '.') : '–',
                        $ag->status,
                        $ag->origem ?? 'manual',
                    ], ';');
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
