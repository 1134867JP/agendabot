<?php

use App\Jobs\BackupELimparHistoricoJob;
use App\Jobs\EnviarLembretesJob;
use App\Jobs\ExpirarConversasInativasJob;
use App\Jobs\GerarCobrancaBotJob;
use App\Jobs\MonitorarConexoesWhatsappJob;
use App\Jobs\RecoverOutboundMessagesJob;
use App\Jobs\SendAppointmentConfirmationsJob;
use App\Jobs\VerificarTrialExpiradoJob;
use App\Models\Tenant;
use App\Support\RuntimeHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bloquear tenants past_due há mais de 3 dias
Schedule::call(function () {
    Tenant::where('subscription_status', 'past_due')
        ->where('subscription_ends_at', '<', now()->subDays(3))
        ->update(['subscription_status' => 'blocked']);
})->name('subscriptions:block-past-due')->daily()->withoutOverlapping(10);

// Enviar lembretes D-1 para agendamentos de amanhã
Schedule::job(new EnviarLembretesJob, 'notifications')
    ->name('notifications:appointment-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping(60);
Schedule::job(new SendAppointmentConfirmationsJob, 'notifications')
    ->name('notifications:appointment-confirmations')
    ->hourly()
    ->withoutOverlapping(60);

// Verificar trials expirados diariamente (00:30)
Schedule::job(new VerificarTrialExpiradoJob, 'financial')
    ->name('subscriptions:expire-trials')
    ->dailyAt('00:30')
    ->withoutOverlapping(60);

// Encerrar conversas sem atividade há mais de 30 minutos
Schedule::job(new ExpirarConversasInativasJob, 'maintenance')
    ->name('conversations:expire-inactive')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30);

// Aplica diariamente a retenção contratada (Starter 30d, Pro 90d, Business completo).
Schedule::call(function (): void {
    Tenant::query()->where('ativo', true)->eachById(
        fn (Tenant $tenant) => BackupELimparHistoricoJob::dispatch($tenant)->onQueue('maintenance')
    );
})->name('conversations:retention')->dailyAt('02:15')->withoutOverlapping(120);

// Gerar cobrança variável de agendamentos via bot (todo dia 1 às 08:00)
Schedule::job(new GerarCobrancaBotJob, 'financial')
    ->name('billing:generate-bot-charges')
    ->monthlyOn(1, '08:00')
    ->withoutOverlapping(120);

// Reenfileirar mensagens persistidas que ficaram sem execução ou com lock órfão.
Schedule::job(new RecoverOutboundMessagesJob, 'maintenance')
    ->name('messages:recover-outbound')
    ->everyMinute()
    ->withoutOverlapping(5);

// Manter o status local do WhatsApp alinhado ao estado real da Evolution.
Schedule::job(new MonitorarConexoesWhatsappJob, 'maintenance')
    ->name('whatsapp:connection-watchdog')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

// Alertar quando novos jobs caírem em failed_jobs
Schedule::command('jobs:alertar-falhas')
    ->name('jobs:alert-failures')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

// Confirma que o processo do scheduler está efetivamente executando a agenda.
Schedule::call(fn () => RuntimeHealth::touchScheduler())
    ->name('runtime:scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(2);
