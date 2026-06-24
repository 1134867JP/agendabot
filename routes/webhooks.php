<?php

use App\Http\Controllers\WebhookController;
use App\Http\Controllers\AsaasWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/{tenantSlug}', [WebhookController::class, 'handle'])->name('webhook');
Route::post('/asaas/webhook', [AsaasWebhookController::class, 'handle'])->name('asaas.webhook');
