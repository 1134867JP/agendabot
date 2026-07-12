<?php

use App\Jobs\EnviarLembreteConsultaV2;
use App\Jobs\EnviarLembretesJob;
use App\Jobs\ExpirarConversasInativasJob;
use App\Jobs\GerarCobrancaBotJob;
use App\Jobs\SendAppointmentConfirmationsJob;
use App\Jobs\VerificarTrialExpiradoJob;
use App\Models\Agendamento;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verificar trials vencidos diariamente
Schedule::call(function () {
    Tenant::where('subscription_status', 'trial')
        ->where('trial_ends_at', '<', now())
        ->update(['subscription_status' => 'past_due', 'subscription_ends_at' => now()]);
})->daily();

// Bloquear tenants past_due há mais de 3 dias
Schedule::call(function () {
    Tenant::where('subscription_status', 'past_due')
        ->where('subscription_ends_at', '<', now()->subDays(3))
        ->update(['subscription_status' => 'blocked']);
})->daily();

// Enviar lembretes D-1 para agendamentos de amanhã
Schedule::job(new EnviarLembretesJob, 'notifications')->dailyAt('09:00');
Schedule::job(new SendAppointmentConfirmationsJob, 'notifications')->hourly();

// Enviar lembretes v2 — um job por agendamento do dia seguinte (08:00)
Schedule::call(function () {
    $amanha = Carbon::tomorrow();
    Agendamento::with(['tenant', 'recurso', 'servico'])
        ->whereDate('data_hora', $amanha)
        ->where('status', '!=', 'cancelado')
        ->each(fn (Agendamento $ag) => EnviarLembreteConsultaV2::dispatch($ag)->onQueue('notifications'));
})->dailyAt('08:00');

// Verificar trials expirados diariamente (00:30)
Schedule::job(new VerificarTrialExpiradoJob, 'financial')->dailyAt('00:30');

// Encerrar conversas sem atividade há mais de 30 minutos
Schedule::job(new ExpirarConversasInativasJob, 'maintenance')->everyFifteenMinutes();

// Gerar cobrança variável de agendamentos via bot (todo dia 1 às 08:00)
Schedule::job(new GerarCobrancaBotJob, 'financial')->monthlyOn(1, '08:00');

// Alertar quando novos jobs caírem em failed_jobs
Schedule::command('jobs:alertar-falhas')->everyFiveMinutes();
