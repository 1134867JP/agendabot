<?php

use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Rotas de webhook sem middleware (sem session, sem Inertia, sem CSRF)
Route::post('/webhook/{tenantSlug}', [WebhookController::class, 'handle'])
    ->name('webhook');

Route::post('/asaas/webhook', [AsaasWebhookController::class, 'handle'])
    ->name('asaas.webhook');
