<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\AsaasApiException;
use App\Exceptions\HorarioIndisponivelException;
use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Profissional;
use App\Models\Recurso;
use App\Models\Servico;
use App\Services\AgendamentoService;
use App\Services\AsaasService;
use App\Services\CatalogoServicos;
use App\Services\OutboundMessageService;
use App\Support\Csv;
use App\Support\TenantAccess;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgendamentoController extends Controller
{
    public function __construct(
        private AgendamentoService $agendamentoService,
        private CatalogoServicos $catalogo,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $telefoneCliente = preg_replace('/\D+/', '', (string) $request->query('cliente'));
        $clienteInicial = $telefoneCliente !== ''
            ? TenantAccess::scopeClientes(Cliente::query(), $tenant)
                ->where('tenant_id', $tenant->id)
                ->where('telefone', $telefoneCliente)
                ->first(['nome', 'telefone'])
            : null;

        $query = TenantAccess::scopeAgendamentos(Agendamento::where('tenant_id', $tenant->id), $tenant)
            ->with(['recurso', 'profissional', 'servico', 'itensServico'])
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
            'tenant' => $tenant,
            'sinalDisponivel' => (bool) $tenant->asaasWalletId(),
            'agendamentos' => $query->paginate(20)->withQueryString()
                ->through(fn (Agendamento $agendamento) => $agendamento->append('servicos_nome')),
            'agenda' => $tenant->agendaConfig(),
            'recursos' => $tenant->agendaUsaRecursos() ? $tenant->recursos()->where('ativo', true)->get() : collect(),
            'profissionais' => ! $tenant->agendaUsaProfissionais()
                ? collect()
                : $tenant->profissionais()
                    ->where('ativo', true)
                    ->when(TenantAccess::profissionalId($tenant), fn ($query, $id) => $query->whereKey($id))
                    ->orderBy('nome')
                    ->get(['id', 'nome']),
            'servicos' => $tenant->servicos()
                ->where('ativo', true)
                ->when(TenantAccess::profissionalId($tenant), fn ($query, $id) => $query->whereHas('profissionais', fn ($profissionais) => $profissionais->whereKey($id)))
                ->with(['profissionais:id', 'recursos:id'])
                ->orderBy('nome')
                ->get(['id', 'tenant_id', 'nome', 'duracao_minutos', 'valor_min', 'valor_max', 'requer_profissional', 'requer_recurso'])
                ->map(fn (Servico $servico) => [
                    'id' => $servico->id,
                    'nome' => $servico->nome,
                    'duracao_minutos' => (int) ($servico->duracao_minutos ?? 30),
                    'profissional_ids' => $servico->profissionais->pluck('id')->values(),
                    'personalizado_por_profissional' => self::personalizacoes($servico),
                    'recurso_ids' => $servico->recursos->pluck('id')->values(),
                    'requer_profissional' => $servico->requer_profissional,
                    'requer_recurso' => $servico->requer_recurso,
                ]),
            'clienteInicial' => $clienteInicial
                ? ['nome' => $clienteInicial->nome, 'telefone' => $clienteInicial->telefone]
                : null,
            'filtros' => $request->only(['data', 'recurso_id', 'status', 'busca']),
        ]);
    }

    /**
     * Duração e valor próprios de cada profissional para o serviço, para o
     * formulário mostrar o total certo antes de salvar.
     *
     * @return array<int, array{valor: ?float, duracao_minutos: ?int}>
     */
    public static function personalizacoes(Servico $servico): array
    {
        return $servico->profissionais
            ->filter(fn ($p) => $p->pivot->valor !== null || $p->pivot->duracao_minutos !== null)
            ->mapWithKeys(fn ($p) => [$p->id => [
                'valor' => $p->pivot->valor !== null ? (float) $p->pivot->valor : null,
                'duracao_minutos' => $p->pivot->duracao_minutos !== null ? (int) $p->pivot->duracao_minutos : null,
            ]])
            ->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $agendaUsaApenasRecursos = $tenant->agendaUsaApenasRecursos();

        $request->merge([
            'cliente_telefone' => preg_replace('/\D+/', '', (string) $request->input('cliente_telefone')),
        ]);

        $tenantId = $tenant->id;
        $rotuloRecurso = strtolower($tenant->agendaConfig()['recurso']);
        $validated = $request->validate([
            // Agenda só por recurso (quadras) nunca aceita profissional como fallback.
            'recurso_id' => [Rule::requiredIf($agendaUsaApenasRecursos), 'nullable', 'integer', Rule::exists('recursos', 'id')->where('tenant_id', $tenantId)],
            'profissional_id' => [Rule::prohibitedIf($agendaUsaApenasRecursos), 'nullable', 'integer', Rule::exists('profissionais', 'id')->where('tenant_id', $tenantId)],
            'servico_id' => ['nullable', 'integer', Rule::exists('servicos', 'id')->where('tenant_id', $tenantId)],
            'servico_ids' => ['nullable', 'array', 'max:5'],
            'servico_ids.*' => ['integer', Rule::exists('servicos', 'id')->where('tenant_id', $tenantId)->where('ativo', true)],
            'cliente_nome' => ['required', 'string', 'max:255'],
            'cliente_telefone' => ['required', 'string', 'regex:/^(?:55)?[1-9][0-9]{9,10}$/'],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after:inicio'],
            'observacoes' => ['nullable', 'string'],
            'notificar_cliente' => ['boolean'],
        ], [
            'cliente_telefone.regex' => 'Informe um telefone válido com DDD, por exemplo: 54999999999.',
            'recurso_id.required' => "Selecione uma {$rotuloRecurso}.",
            'profissional_id.prohibited' => "As reservas desta agenda são feitas por {$rotuloRecurso}, não por profissional.",
        ]);

        $profissionalIdLogado = TenantAccess::profissionalId($tenant);
        abort_if($profissionalIdLogado && (int) ($validated['profissional_id'] ?? 0) !== $profissionalIdLogado, 403);

        // O primeiro serviço define as regras de agenda; os demais compõem o combo.
        $servicoIds = $this->catalogo->normalizarIds($validated['servico_ids'] ?? [$validated['servico_id'] ?? null]);
        $validated['servico_id'] = $servicoIds[0] ?? null;

        $servico = null;
        $profissional = ! empty($validated['profissional_id'])
            ? Profissional::where('tenant_id', $tenant->id)->findOrFail($validated['profissional_id'])
            : null;
        if (! empty($validated['servico_id'])) {
            $servico = Servico::where('tenant_id', $tenant->id)
                ->where('ativo', true)
                ->findOrFail($validated['servico_id']);

            if ($profissional && $this->catalogo->naoAtendidos($profissional, $servicoIds)->isNotEmpty()) {
                $nomes = $this->catalogo->naoAtendidos($profissional, $servicoIds)->join(', ', ' e ');

                return back()->withErrors(['servico_id' => "{$profissional->nome} não realiza: {$nomes}."]);
            }

            if ($servico->requer_profissional && empty($validated['profissional_id'])) {
                return back()->withErrors(['profissional_id' => 'Selecione quem realizará este serviço.']);
            }
            if ($servico->requer_recurso && empty($validated['recurso_id'])) {
                return back()->withErrors(['recurso_id' => 'Selecione o '.strtolower($tenant->agendaConfig()['recurso']).' necessário para este serviço.']);
            }
            if (! empty($validated['recurso_id']) && $servico->recursos()->exists()
                && ! $servico->recursos()->whereKey($validated['recurso_id'])->exists()) {
                return back()->withErrors(['recurso_id' => 'Este serviço não está disponível neste recurso.']);
            }
        } elseif ($agendaUsaApenasRecursos && empty($validated['recurso_id'])) {
            return back()->withErrors(['recurso_id' => 'Selecione uma '.strtolower($tenant->agendaConfig()['recurso']).'.']);
        } elseif (! $agendaUsaApenasRecursos && empty($validated['profissional_id']) && empty($validated['recurso_id'])) {
            return back()->withErrors(['profissional_id' => 'Selecione uma agenda para este agendamento.']);
        }

        $montagem = $servicoIds !== [] ? $this->catalogo->montar($tenant, $servicoIds, $profissional) : null;

        $inicio = Carbon::parse($validated['inicio']);
        $fim = $montagem && $montagem['duracao_minutos'] > 0
            ? $inicio->copy()->addMinutes($montagem['duracao_minutos'])
            : Carbon::parse($validated['fim']);

        $dados = [
            'tenant_id' => $tenant->id,
            'cliente_nome' => $validated['cliente_nome'],
            'cliente_telefone' => $validated['cliente_telefone'],
            'inicio' => $inicio,
            'fim' => $fim,
            'observacoes' => $validated['observacoes'] ?? null,
            'origem' => 'manual',
            'status' => 'confirmado',
            'servico_id' => $servico?->id,
            'montagem' => $montagem,
        ];

        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $tenant->id, 'telefone' => $validated['cliente_telefone']],
            ['nome' => trim($validated['cliente_nome'])],
        );
        $dados['cliente_id'] = $cliente->id;

        if ($montagem && $montagem['valor_total'] !== null) {
            $dados['valor_total'] = $montagem['valor_total'];
        }

        if (! empty($validated['recurso_id'])) {
            $recurso = Recurso::where('tenant_id', $tenant->id)->findOrFail($validated['recurso_id']);
            $dados['recurso_id'] = $validated['recurso_id'];
            if (! array_key_exists('valor_total', $dados)) {
                $dados['valor_total'] = $recurso->valor_hora
                    ? $recurso->valor_hora * $inicio->diffInMinutes($fim) / 60
                    : null;
            }
        }
        if ($profissional) {
            $dados['profissional_id'] = $profissional->id;
            $dados['data_hora'] = $inicio;
            $dados['duracao_minutos'] = (int) $inicio->diffInMinutes($fim);
        }

        try {
            $agendamento = $this->agendamentoService->criar($tenant, $dados);
        } catch (HorarioIndisponivelException $e) {
            return back()->withErrors(['inicio' => $e->getMessage()]);
        }

        if ($agendamento->profissional_id) {
            Conversa::where('tenant_id', $tenant->id)
                ->where('telefone_cliente', $agendamento->cliente_telefone)
                ->update(['profissional_id' => $agendamento->profissional_id]);
        }

        return back()->with('success', 'Agendamento criado com sucesso.');
    }

    public function update(Request $request, Agendamento $agendamento): RedirectResponse
    {
        $tenant = app('tenant');
        TenantAccess::assertAgendamento($agendamento, $tenant);

        $request->merge([
            'cliente_telefone' => preg_replace('/\D+/', '', (string) $request->input('cliente_telefone')),
        ]);

        $validated = $request->validate([
            'recurso_id' => ['nullable', 'integer', Rule::exists('recursos', 'id')->where('tenant_id', $tenant->id)],
            'cliente_nome' => ['required', 'string', 'max:255'],
            'cliente_telefone' => ['required', 'string', 'regex:/^(?:55)?[1-9][0-9]{9,10}$/'],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after:inicio'],
            'observacoes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:confirmado,cancelado,concluido'],
        ], [
            'cliente_telefone.regex' => 'Informe um telefone válido com DDD, por exemplo: 54999999999.',
        ]);

        try {
            $this->agendamentoService->atualizar($agendamento, $validated);
        } catch (HorarioIndisponivelException $e) {
            return back()->withErrors(['inicio' => $e->getMessage()]);
        }

        return back()->with('success', 'Agendamento atualizado.');
    }

    public function cancelar(Agendamento $agendamento): RedirectResponse
    {
        TenantAccess::assertAgendamento($agendamento, app('tenant'));
        $this->agendamentoService->cancelar($agendamento);

        return back()->with('success', 'Agendamento cancelado.');
    }

    public function concluir(Agendamento $agendamento): RedirectResponse
    {
        TenantAccess::assertAgendamento($agendamento, app('tenant'));
        $agendamento->update(['status' => 'concluido']);

        return back()->with('success', 'Agendamento concluído.');
    }

    public function marcarNoShow(Agendamento $agendamento): RedirectResponse
    {
        TenantAccess::assertAgendamento($agendamento, app('tenant'));
        $agendamento->update(['no_show' => true, 'status' => 'concluido']);

        return back()->with('success', 'Ausência registrada.');
    }

    public function gerarSinal(Request $request, Agendamento $agendamento, AsaasService $asaas, OutboundMessageService $outboundMessages): RedirectResponse
    {
        $tenant = app('tenant');
        TenantAccess::assertAgendamento($agendamento, $tenant);
        $data = $request->validate([
            'valor' => ['required', 'numeric', 'min:5', 'max:9999'],
            'enviar_whatsapp' => ['boolean'],
        ], [
            'valor.min' => 'O Asaas aceita cobranças a partir de R$ 5,00.',
        ]);

        if (! $tenant->asaasWalletId()) {
            return back()->withErrors(['valor' => 'Cadastre a carteira Asaas da barbearia em Configurações → Pagamentos para receber o sinal na sua conta.']);
        }

        try {
            return Cache::lock("asaas:deposit:appointment:{$agendamento->id}", 45)->block(5, function () use ($agendamento, $asaas, $data, $tenant, $outboundMessages): RedirectResponse {
                $agendamento->refresh();

                if (in_array($agendamento->deposit_status, ['pending', 'paid'], true)
                    && $agendamento->deposit_payment_id
                    && $agendamento->deposit_payment_url) {
                    return back()->with('info', 'Este agendamento já possui uma cobrança de sinal.');
                }

                try {
                    $payment = $asaas->criarSinalAgendamento($agendamento, (float) $data['valor']);
                } catch (AsaasApiException $e) {
                    return back()->withErrors(['valor' => $e->getMessage()]);
                }
                $agendamento->update([
                    'deposit_status' => 'pending',
                    'deposit_amount' => $data['valor'],
                    'deposit_payment_id' => $payment['id'],
                    'deposit_payment_url' => $payment['url'],
                ]);

                if (($data['enviar_whatsapp'] ?? true) && $payment['url'] && $tenant->whatsapp_conectado && $agendamento->cliente_telefone) {
                    $quando = Carbon::parse($agendamento->data_hora ?? $agendamento->inicio)->locale('pt_BR')->translatedFormat('d/m \\à\\s H:i');
                    $valor = number_format((float) $data['valor'], 2, ',', '.');
                    $outboundMessages->queue(
                        tenant: $tenant,
                        telefone: $agendamento->cliente_telefone,
                        conteudo: "Olá, {$agendamento->cliente_nome}! Para garantir seu horário de {$quando} na {$tenant->nome}, pague o sinal de R$ {$valor} pelo PIX:\n{$payment['url']}",
                        purpose: 'deposit_request',
                        idempotencyKey: "deposit-request:{$agendamento->id}:{$payment['id']}",
                        agendamento: $agendamento,
                    );

                    return back()->with('success', 'Cobrança de sinal gerada e enviada ao cliente pelo WhatsApp.');
                }

                return back()->with('success', 'Cobrança de sinal gerada. Copie o link e envie ao cliente.');
            });
        } catch (LockTimeoutException) {
            return back()->with('erro', 'A cobrança já está sendo gerada. Aguarde alguns instantes.');
        }
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

        $query = TenantAccess::scopeAgendamentos(Agendamento::where('tenant_id', $tenant->id), $tenant)
            ->with(['recurso', 'profissional', 'servico', 'itensServico'])
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

        $filename = 'agendamentos-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Cliente', 'Telefone', 'Serviço / Recurso', 'Data/Hora', 'Duração (min)', 'Valor', 'Status', 'Origem'], ';');

            $query->chunk(500, function ($agendamentos) use ($handle) {
                foreach ($agendamentos as $ag) {
                    $dataHora = $ag->data_hora ?? $ag->inicio;
                    $servico = $ag->descricaoServicos() ?? $ag->recurso?->nome ?? '–';
                    $duracao = $ag->duracao_minutos ?? (
                        ($ag->inicio && $ag->fim)
                            ? Carbon::parse($ag->inicio)->diffInMinutes($ag->fim)
                            : '–'
                    );
                    fputcsv($handle, Csv::row([
                        $ag->id,
                        $ag->cliente_nome ?? $ag->cliente?->nome ?? '–',
                        $ag->cliente_telefone ?? $ag->cliente?->telefone ?? '–',
                        $servico,
                        $dataHora ? Carbon::parse($dataHora)->format('d/m/Y H:i') : '–',
                        $duracao,
                        $ag->valor_total ? number_format($ag->valor_total, 2, ',', '.') : '–',
                        $ag->status,
                        $ag->origem ?? 'manual',
                    ]), ';');
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
