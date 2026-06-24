<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SuperAdmin;
use App\Http\Controllers\Tenant;
use App\Http\Controllers\Tenant\ClienteController;
use App\Http\Controllers\Tenant\ConversaController;
use App\Http\Controllers\Tenant\HorarioProfissionalController;
use App\Http\Controllers\Tenant\OpcaoExtraController;
use App\Http\Controllers\Tenant\ProfissionalController;
use App\Http\Controllers\Tenant\ServicoController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

// Site público
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/precos', [LandingController::class, 'precos'])->name('precos');

// Onboarding
Route::get('/cadastro', [OnboardingController::class, 'step1'])->name('onboarding.step1');
Route::post('/cadastro', [OnboardingController::class, 'step1Store']);
Route::middleware('auth')->group(function () {
    Route::get('/cadastro/plano', [OnboardingController::class, 'step2'])->name('onboarding.step2');
    Route::post('/cadastro/checkout', [OnboardingController::class, 'checkout'])->name('onboarding.checkout');
    Route::get('/cadastro/sucesso', [OnboardingController::class, 'sucesso'])->name('onboarding.sucesso');
    Route::post('/cadastro/pular', [OnboardingController::class, 'pularPagamento'])->name('onboarding.pular');
});

Route::middleware('auth')->group(function () {
    // Seleção de tenant (tela inicial para donos)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/tenants/{tenant}/selecionar', [TenantController::class, 'selecionar'])->name('tenants.selecionar');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tela de renovação (bloqueio)
    Route::middleware(['tenant'])->group(function () {
        Route::get('/renovar', [SubscriptionController::class, 'renovar'])->name('tenant.renovar');
        Route::post('/renovar', [SubscriptionController::class, 'processarRenovacao'])->name('tenant.renovar.store');
        Route::get('/assinar/cancelar', [SubscriptionController::class, 'cancelar'])->name('tenant.cancelar');
    });

    // ── Painel do Dono ────────────────────────────────────────────────────────
    Route::middleware(['tenant', 'subscription'])->prefix('painel')->name('tenant.')->group(function () {
        Route::get('/', [Tenant\DashboardController::class, 'index'])->name('dashboard');

        // Agenda visual
        Route::get('agenda', [Tenant\AgendaController::class, 'index'])->name('agenda');
        Route::get('agenda/disponibilidade', [Tenant\AgendaController::class, 'disponibilidade'])->name('agenda.disponibilidade');

        // Agendamentos
        Route::get('agendamentos', [Tenant\AgendamentoController::class, 'index'])->name('agendamentos.index');
        Route::post('agendamentos', [Tenant\AgendamentoController::class, 'store'])->name('agendamentos.store');
        Route::patch('agendamentos/{agendamento}/cancelar', [Tenant\AgendamentoController::class, 'cancelar'])->name('agendamentos.cancelar');
        Route::patch('agendamentos/{agendamento}/concluir', [Tenant\AgendamentoController::class, 'concluir'])->name('agendamentos.concluir');

        // Recursos
        Route::resource('recursos', Tenant\RecursoController::class)->except(['show']);

        // Horários
        Route::post('recursos/{recurso}/horarios', [Tenant\HorarioController::class, 'sync'])->name('horarios.sync');

        // Profissionais
        Route::resource('profissionais', ProfissionalController::class)->except(['show']);
        Route::post('profissionais/{profissional}/horarios', [HorarioProfissionalController::class, 'sync'])
            ->name('profissionais.horarios.sync');

        // Serviços
        Route::resource('servicos', ServicoController::class)->except(['show']);

        // Opções extras (convênios, pagamentos)
        Route::resource('opcoes-extras', OpcaoExtraController::class)->except(['show']);

        // Clientes
        Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
        Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');

        // Conversas WhatsApp
        Route::get('conversas', [ConversaController::class, 'index'])->name('conversas.index');
        Route::get('conversas/{conversa}/mensagens', [ConversaController::class, 'mensagens'])->name('conversas.mensagens');
        Route::post('conversas/{conversa}/assumir', [ConversaController::class, 'assumir'])->name('conversas.assumir');
        Route::post('conversas/{conversa}/devolver', [ConversaController::class, 'devolver'])->name('conversas.devolver');
        Route::post('conversas/{conversa}/enviar', [ConversaController::class, 'enviarMensagem'])->name('conversas.enviar');

        // Config bot
        Route::put('configuracoes/bot', [Tenant\ConfiguracaoController::class, 'updateBot'])->name('configuracoes.bot');

        // WhatsApp
        Route::get('whatsapp', [Tenant\WhatsAppController::class, 'index'])->name('whatsapp');
        Route::get('whatsapp/qrcode', [Tenant\WhatsAppController::class, 'qrcode'])->name('whatsapp.qrcode');
        Route::get('whatsapp/status', [Tenant\WhatsAppController::class, 'status'])->name('whatsapp.status');

        // Configurações
        Route::get('configuracoes', [Tenant\ConfiguracaoController::class, 'index'])->name('configuracoes.index');
        Route::put('configuracoes', [Tenant\ConfiguracaoController::class, 'update'])->name('configuracoes.update');
    });
});

// ── Super Admin ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', [SuperAdmin\DashboardController::class, 'index'])->name('dashboard');

    Route::resource('tenants', SuperAdmin\TenantController::class);
    Route::patch('tenants/{tenant}/toggle-ativo', [SuperAdmin\TenantController::class, 'toggleAtivo'])->name('tenants.toggle-ativo');
    Route::post('tenants/{tenant}/impersonar', [SuperAdmin\TenantController::class, 'impersonar'])->name('tenants.impersonar');
    Route::delete('impersonar', [SuperAdmin\TenantController::class, 'pararImpersonar'])->name('impersonar.parar');

    Route::get('agendamentos', [SuperAdmin\AgendamentoController::class, 'index'])->name('agendamentos');

    Route::get('logs',      [SuperAdmin\LogController::class, 'index'])->name('logs');
    Route::get('logs/json', [SuperAdmin\LogController::class, 'json'])->name('logs.json');

    Route::get('tokens', [SuperAdmin\TokenUsageController::class, 'index'])->name('tokens');
});

require __DIR__.'/auth.php';
