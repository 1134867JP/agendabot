<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\CobrancaBot;
use App\Models\Conversa;
use App\Models\OperationalEvent;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');
        $modoAgendamento = ($tenant->modo_bot ?? 'agendamento') === 'agendamento';

        $profissionalAtivo = $tenant->profissionais()->where('ativo', true)->first();
        $recursoComHorario = $tenant->recursos()->where('ativo', true)
            ->whereHas('horariosFuncionamento')
            ->exists();

        $setupCompleto = [
            'profissionais' => $tenant->profissionais()->where('ativo', true)->exists(),
            'servicos'      => $tenant->servicos()->where('ativo', true)->exists(),
            'recursos'      => $tenant->recursos()->where('ativo', true)->exists(),
            'whatsapp'      => (bool) $tenant->whatsapp_conectado,
            'bot_config'    => ! empty($tenant->ramo_negocio),
            'horario'       => ($profissionalAtivo && $profissionalAtivo->horarios()->exists())
                || $recursoComHorario,
        ];

        $agendamentos = Agendamento::where('tenant_id', $tenant->id);
        $conversas = Conversa::where('tenant_id', $tenant->id);

        $aguardandoHumano = (clone $conversas)
            ->where('status_v2', 'aguardando_humano')
            ->count();

        $conversasNaoLidas = (clone $conversas)
            ->whereNotNull('ultima_mensagem_em')
            ->where(function ($query) {
                $query->whereNull('ultima_leitura_em')
                    ->orWhereColumn('ultima_mensagem_em', '>', 'ultima_leitura_em');
            })
            ->count();

        $falhasRecentes = OperationalEvent::where('tenant_id', $tenant->id)
            ->where('type', 'integration_failure')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $depositosPendentes = $modoAgendamento
            ? (clone $agendamentos)
                ->where('inicio', '>=', now())
                ->where('deposit_status', 'pending')
                ->count()
            : 0;

        $pendencias = collect();

        if (! $tenant->whatsapp_conectado) {
            $pendencias->push([
                'id' => 'whatsapp',
                'tone' => 'danger',
                'title' => 'WhatsApp desconectado',
                'description' => 'O atendimento automático está interrompido.',
                'action' => 'Reconectar',
                'href' => route('tenant.whatsapp'),
            ]);
        }

        if ($aguardandoHumano > 0) {
            $pendencias->push([
                'id' => 'handoff',
                'tone' => 'warning',
                'title' => "{$aguardandoHumano} conversa".($aguardandoHumano === 1 ? '' : 's').' aguardando atendimento',
                'description' => 'O bot transferiu estes clientes para sua equipe.',
                'action' => 'Atender agora',
                'href' => route('tenant.conversas.index', ['status_v2' => 'aguardando_humano']),
            ]);
        } elseif ($conversasNaoLidas > 0) {
            $pendencias->push([
                'id' => 'unread',
                'tone' => 'neutral',
                'title' => "{$conversasNaoLidas} conversa".($conversasNaoLidas === 1 ? '' : 's').' não lida'.($conversasNaoLidas === 1 ? '' : 's'),
                'description' => 'Existem novas mensagens desde sua última leitura.',
                'action' => 'Ver conversas',
                'href' => route('tenant.conversas.index'),
            ]);
        }

        if ($depositosPendentes > 0) {
            $pendencias->push([
                'id' => 'deposits',
                'tone' => 'warning',
                'title' => $depositosPendentes === 1 ? '1 sinal pendente' : "{$depositosPendentes} sinais pendentes",
                'description' => 'Confira os pagamentos antes dos próximos atendimentos.',
                'action' => 'Revisar reservas',
                'href' => route('tenant.agendamentos.index'),
            ]);
        }

        if ($falhasRecentes > 0) {
            $pendencias->push([
                'id' => 'failures',
                'tone' => 'danger',
                'title' => "{$falhasRecentes} falha".($falhasRecentes === 1 ? '' : 's').' de integração nas últimas 24h',
                'description' => 'Confira se WhatsApp, Calendar e automações estão funcionando.',
                'action' => 'Ver desempenho',
                'href' => route('tenant.analytics'),
            ]);
        }

        return Inertia::render('Tenant/Dashboard', [
            'tenant' => $tenant,
            'stats' => [
                'agendamentos_hoje' => (clone $agendamentos)
                    ->whereDate('inicio', today())
                    ->whereIn('status', ['confirmado', 'agendado'])
                    ->count(),
                'agendamentos_semana' => (clone $agendamentos)
                    ->whereBetween('inicio', [now()->startOfWeek(), now()->endOfWeek()])
                    ->whereIn('status', ['confirmado', 'agendado'])
                    ->count(),
                'receita_mes' => (clone $agendamentos)
                    ->whereMonth('inicio', now()->month)
                    ->whereYear('inicio', now()->year)
                    ->where('status', '!=', 'cancelado')
                    ->sum('valor_total'),
                'whatsapp_conectado' => (bool) $tenant->whatsapp_conectado,
                'bot_agendamentos_mes' => (clone $agendamentos)
                    ->whereIn('origem', ['bot', 'whatsapp'])
                    ->where('status', '!=', 'cancelado')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'bot_taxa' => (float) $tenant->taxa_agendamento_bot,
                'conversas_aguardando' => $aguardandoHumano,
                'conversas_nao_lidas' => $conversasNaoLidas,
                'clientes_total' => $tenant->clientes()->count(),
                'falhas_recentes' => $falhasRecentes,
            ],
            'pendencias' => $pendencias->values(),
            'ultima_cobranca_bot' => Schema::hasTable('cobrancas_bot')
                ? CobrancaBot::where('tenant_id', $tenant->id)
                    ->orderByDesc('periodo')
                    ->first(['periodo', 'quantidade_agendamentos', 'valor_total', 'status'])
                : null,
            'proximos_agendamentos' => $modoAgendamento
                ? (clone $agendamentos)
                    ->with('recurso')
                    ->where('inicio', '>=', now())
                    ->whereIn('status', ['confirmado', 'agendado'])
                    ->orderBy('inicio')
                    ->limit(5)
                    ->get()
                : [],
            'setup_completo' => $setupCompleto,
        ]);
    }
}
