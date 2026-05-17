<?php

use App\Jobs\EnviarLembretesJob;
use App\Models\Tenant;
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
Schedule::job(new EnviarLembretesJob)->dailyAt('09:00');
