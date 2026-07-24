<?php

use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/{tenantSlug}', [WebhookController::class, 'handle'])->middleware(['body.limit:1048576', 'throttle:evolution-webhook'])->name('webhook');
Route::post('/asaas/webhook', [AsaasWebhookController::class, 'handle'])->middleware(['body.limit:262144', 'throttle:40,1'])->name('asaas.webhook');

// Endpoint de monitoramento externo (somente leitura, protegido por MONITOR_API_TOKEN)
Route::get('/monitor/erros', [MonitorController::class, 'erros'])
    ->middleware(['throttle:30,1', 'monitor.token'])
    ->name('monitor.erros');
