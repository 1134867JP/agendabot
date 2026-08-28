<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\ClienteController;
use App\Http\Controllers\Tenant\ConversaController;
use App\Http\Controllers\Tenant\EquipeController;
use App\Http\Controllers\Tenant\HorarioProfissionalController;
use App\Http\Controllers\Tenant\OpcaoExtraController;
use App\Http\Controllers\Tenant\ProfissionalController;
use App\Http\Controllers\Tenant\ServicoController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Site público
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/precos', [LandingController::class, 'precos'])->name('precos');
Route::get('/health', HealthController::class)->name('health');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

// Onboarding
Route::get('/cadastro', [OnboardingController::class, 'step1'])->name('onboarding.step1');
Route::post('/cadastro', [OnboardingController::class, 'step1Store'])->middleware('throttle:6,1');
Route::middleware('auth')->group(function () {
    Route::get('/cadastro/plano', [OnboardingController::class, 'step2'])->name('onboarding.step2');
    Route::post('/cadastro/checkout', [OnboardingController::class, 'checkout'])->name('onboarding.checkout');
    Route::get('/cadastro/personalizar', [OnboardingController::class, 'step3'])->name('onboarding.step3');
    Route::post('/cadastro/personalizar', [OnboardingController::class, 'step3Store'])->name('onboarding.step3.store');
    Route::get('/cadastro/sucesso', [OnboardingController::class, 'sucesso'])->name('onboarding.sucesso');
    Route::post('/cadastro/pular', [OnboardingController::class, 'pularPagamento'])->name('onboarding.pular');
});

Route::middleware('auth')->group(function () {
    // Seleção de tenant (tela inicial para donos)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/tenants/{tenant}/selecionar', [TenantController::class, 'selecionar'])->name('tenants.selecionar');
    Route::get('/tenants/novo', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tela de renovação (bloqueio)
    Route::middleware(['tenant'])->group(function () {
        Route::get('/renovar', [SubscriptionController::class, 'renovar'])->middleware('tenant.admin')->name('tenant.renovar');
        Route::post('/renovar', [SubscriptionController::class, 'processarRenovacao'])->middleware('tenant.admin')->name('tenant.renovar.store');
        Route::post('/assinar/cancelar', [SubscriptionController::class, 'cancelar'])->middleware(['tenant.admin', 'password.confirm', 'throttle:6,1'])->name('tenant.cancelar');
    });

    // ── Painel do Dono ────────────────────────────────────────────────────────
    Route::middleware(['tenant', 'subscription'])->prefix('painel')->name('tenant.')->group(function () {
        Route::get('/', [Tenant\DashboardController::class, 'index'])->name('dashboard');

        // Agenda visual
        Route::get('agenda', [Tenant\AgendaController::class, 'index'])->name('agenda');
        Route::get('agenda/disponibilidade', [Tenant\AgendaController::class, 'disponibilidade'])->name('agenda.disponibilidade');
        Route::post('agenda/bloqueios', [Tenant\BloqueioAgendaController::class, 'store'])->name('agenda.bloqueios.store');
        Route::delete('agenda/bloqueios/{bloqueio}', [Tenant\BloqueioAgendaController::class, 'destroy'])->name('agenda.bloqueios.destroy');

        // Agendamentos
        Route::get('agendamentos', [Tenant\AgendamentoController::class, 'index'])->name('agendamentos.index');
        Route::get('agendamentos/exportar', [Tenant\AgendamentoController::class, 'exportar'])->middleware('tenant.admin')->name('agendamentos.exportar');
        Route::post('agendamentos', [Tenant\AgendamentoController::class, 'store'])->name('agendamentos.store');
        Route::put('agendamentos/{agendamento}', [Tenant\AgendamentoController::class, 'update'])->name('agendamentos.update');
        Route::patch('agendamentos/{agendamento}/cancelar', [Tenant\AgendamentoController::class, 'cancelar'])->name('agendamentos.cancelar');
        Route::patch('agendamentos/{agendamento}/concluir', [Tenant\AgendamentoController::class, 'concluir'])->name('agendamentos.concluir');
        Route::delete('agendamentos/{agendamento}', [Tenant\AgendamentoController::class, 'destroy'])->middleware(['tenant.admin', 'password.confirm'])->name('agendamentos.destroy');

        // Analytics
        Route::get('analytics', [Tenant\AnalyticsController::class, 'index'])->name('analytics');
        Route::post('lista-espera', [Tenant\WaitlistController::class, 'store'])->name('waitlist.store');
        Route::delete('lista-espera/{waitlistEntry}', [Tenant\WaitlistController::class, 'destroy'])->name('waitlist.destroy');
        Route::patch('agendamentos/{agendamento}/no-show', [Tenant\AgendamentoController::class, 'marcarNoShow'])->name('agendamentos.no-show');
        Route::post('agendamentos/{agendamento}/sinal', [Tenant\AgendamentoController::class, 'gerarSinal'])->middleware('tenant.admin')->name('agendamentos.sinal');
        Route::get('integracoes/google-calendar/conectar', [Tenant\GoogleCalendarController::class, 'connect'])->middleware('tenant.admin')->name('calendar.connect');
        Route::get('integracoes/google-calendar/callback', [Tenant\GoogleCalendarController::class, 'callback'])->middleware('tenant.admin')->name('calendar.callback');
        Route::delete('integracoes/google-calendar', [Tenant\GoogleCalendarController::class, 'disconnect'])->middleware(['tenant.admin', 'password.confirm'])->name('calendar.disconnect');

        // Recursos
        Route::resource('recursos', Tenant\RecursoController::class)->except(['show'])->middleware('tenant.admin');

        // Horários
        Route::post('recursos/{recurso}/horarios', [Tenant\HorarioController::class, 'sync'])->middleware('tenant.admin')->name('horarios.sync');

        // Profissionais
        Route::resource('profissionais', ProfissionalController::class)
            ->except(['show'])
            ->parameters(['profissionais' => 'profissional'])
            ->middleware('tenant.admin');
        Route::post('profissionais/{profissional}/horarios', [HorarioProfissionalController::class, 'sync'])
            ->middleware('tenant.admin')
            ->name('profissionais.horarios.sync');

        // Serviços
        Route::resource('servicos', ServicoController::class)->except(['show'])->middleware('tenant.admin');

        // Opções extras (convênios, pagamentos)
        Route::resource('opcoes-extras', OpcaoExtraController::class)
            ->except(['show'])
            ->parameters(['opcoes-extras' => 'opcaoExtra'])
            ->middleware('tenant.admin');

        // Equipe
        Route::get('equipe', [EquipeController::class, 'index'])->middleware('tenant.admin')->name('equipe.index');
        Route::post('equipe', [EquipeController::class, 'store'])->middleware('tenant.admin')->name('equipe.store');
        Route::delete('equipe/{user}', [EquipeController::class, 'destroy'])->middleware(['tenant.admin', 'password.confirm'])->name('equipe.destroy');

        // Clientes
        Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::post('clientes', [ClienteController::class, 'store'])->name('clientes.store');
        Route::delete('clientes', [ClienteController::class, 'destroyBulk'])->middleware(['tenant.admin', 'password.confirm'])->name('clientes.destroy-bulk');
        Route::get('clientes/buscar', [ClienteController::class, 'search'])->name('clientes.search');
        Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
        Route::patch('clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::get('clientes/{cliente}/exportar', [ClienteController::class, 'export'])->middleware('tenant.admin')->name('clientes.export');
        Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->middleware(['tenant.admin', 'password.confirm'])->name('clientes.destroy');

        // Conversas WhatsApp
        Route::get('conversas', [ConversaController::class, 'index'])->name('conversas.index');
        Route::get('conversas/notificacoes', [ConversaController::class, 'notificacoes'])->name('conversas.notificacoes');
        Route::post('conversas/{conversa}/marcar-lida', [ConversaController::class, 'marcarLida'])->name('conversas.marcar-lida');
        Route::patch('conversas/{conversa}/cliente/nome', [ConversaController::class, 'atualizarNomeCliente'])->name('conversas.cliente.nome');
        Route::post('conversas/iniciar', [ConversaController::class, 'iniciar'])->name('conversas.iniciar');
        Route::get('conversas/sincronizacao/status', [ConversaController::class, 'statusSincronizacao'])->name('conversas.sincronizacao.status');
        Route::post('conversas/sincronizar', [ConversaController::class, 'sincronizar'])->name('conversas.sincronizar');
        Route::post('conversas/sincronizacao/cancelar', [ConversaController::class, 'cancelarSincronizacao'])->name('conversas.sincronizacao.cancelar');
        Route::get('conversas/{conversa}/mensagens', [ConversaController::class, 'mensagens'])->name('conversas.mensagens');
        Route::get('conversas/{conversa}/mensagens/{mensagem}/media', [ConversaController::class, 'media'])->name('conversas.media');
        Route::post('conversas/{conversa}/assumir', [ConversaController::class, 'assumir'])->name('conversas.assumir');
        Route::post('conversas/{conversa}/devolver', [ConversaController::class, 'devolver'])->name('conversas.devolver');
        Route::post('conversas/{conversa}/enviar', [ConversaController::class, 'enviarMensagem'])->name('conversas.enviar');

        // Simulador local: não envia mensagens ao WhatsApp nem chama a API de IA
        Route::get('bot/simulador', fn () => Inertia::render('Tenant/BotSimulator'))->name('bot.simulador');

        // Config bot
        Route::put('configuracoes/bot', [Tenant\ConfiguracaoController::class, 'updateBot'])->middleware('tenant.admin')->name('configuracoes.bot');

        // WhatsApp
        Route::get('whatsapp', [Tenant\WhatsAppController::class, 'index'])->name('whatsapp');
        Route::get('whatsapp/qrcode', [Tenant\WhatsAppController::class, 'qrcode'])->middleware('tenant.admin')->name('whatsapp.qrcode');
        Route::get('whatsapp/status', [Tenant\WhatsAppController::class, 'status'])->name('whatsapp.status');
        Route::get('whatsapp/backups/{arquivo}', [Tenant\WhatsAppController::class, 'baixarBackup'])
            ->where('arquivo', 'whatsapp-[0-9]{8}-[0-9]{6}\\.json(?:\\.enc)?')
            ->middleware(['tenant.admin', 'password.confirm'])
            ->name('whatsapp.backup');
        Route::post('whatsapp/desconectar', [Tenant\WhatsAppController::class, 'desconectar'])->middleware(['tenant.admin', 'password.confirm', 'throttle:6,1'])->name('whatsapp.desconectar');

        // Configurações
        Route::get('configuracoes', [Tenant\ConfiguracaoController::class, 'index'])->middleware('tenant.admin')->name('configuracoes.index');
        Route::put('configuracoes', [Tenant\ConfiguracaoController::class, 'update'])->middleware('tenant.admin')->name('configuracoes.update');

        // Triagem (handoff automático bot→humano)
        Route::get('triagem', [Tenant\TriagemController::class, 'index'])->middleware('tenant.admin')->name('triagem.index');
        Route::put('triagem', [Tenant\TriagemController::class, 'update'])->middleware('tenant.admin')->name('triagem.update');

        // Regras de agendamento
        Route::get('regras-agendamento', [Tenant\RegraAgendamentoController::class, 'index'])->middleware('tenant.admin')->name('regras-agendamento.index');
        Route::put('regras-agendamento', [Tenant\RegraAgendamentoController::class, 'update'])->middleware('tenant.admin')->name('regras-agendamento.update');

        // Cobrança variável bot
        Route::get('cobranca/resumo', [Tenant\CobrancaController::class, 'resumo'])->middleware('tenant.admin')->name('cobranca.resumo');
    });
});

// ── Super Admin ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', [SuperAdmin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('tenants', SuperAdmin\TenantController::class)->only(['index']);
    Route::resource('tenants', SuperAdmin\TenantController::class)->only(['create', 'edit'])
        ->middleware('password.confirm');
    Route::resource('tenants', SuperAdmin\TenantController::class)->only(['store', 'update', 'destroy'])
        ->middleware(['password.confirm', 'throttle:10,1']);
    Route::patch('tenants/{tenant}/toggle-ativo', [SuperAdmin\TenantController::class, 'toggleAtivo'])->middleware(['password.confirm', 'throttle:10,1'])->name('tenants.toggle-ativo');
    Route::patch('tenants/{tenant}/toggle-isento', [SuperAdmin\TenantController::class, 'toggleIsento'])->middleware(['password.confirm', 'throttle:10,1'])->name('tenants.toggle-isento');
    Route::post('tenants/{tenant}/impersonar', [SuperAdmin\TenantController::class, 'impersonar'])->middleware(['password.confirm', 'throttle:10,1'])->name('tenants.impersonar');
    Route::delete('impersonar', [SuperAdmin\TenantController::class, 'pararImpersonar'])->name('impersonar.parar');

    Route::get('agendamentos', [SuperAdmin\AgendamentoController::class, 'index'])->name('agendamentos');
    Route::get('financeiro', [SuperAdmin\FinanceiroController::class,  'index'])->name('financeiro');

    Route::get('logs', [SuperAdmin\LogController::class, 'index'])->name('logs');
    Route::get('logs/json', [SuperAdmin\LogController::class, 'json'])->name('logs.json');

    Route::get('jobs', [SuperAdmin\JobsController::class, 'index'])->name('jobs');
    Route::post('jobs/{id}/retry', [SuperAdmin\JobsController::class, 'retry'])->middleware(['password.confirm', 'throttle:10,1'])->name('jobs.retry');
    Route::post('jobs/retry-all', [SuperAdmin\JobsController::class, 'retryAll'])->middleware(['password.confirm', 'throttle:6,1'])->name('jobs.retry-all');
    Route::delete('jobs/{id}', [SuperAdmin\JobsController::class, 'destroy'])->middleware(['password.confirm', 'throttle:10,1'])->name('jobs.destroy');
    Route::delete('jobs', [SuperAdmin\JobsController::class, 'destroyAll'])->middleware(['password.confirm', 'throttle:6,1'])->name('jobs.destroy-all');

    Route::get('tokens', [SuperAdmin\TokenUsageController::class, 'index'])->name('tokens');
});

require __DIR__.'/auth.php';
