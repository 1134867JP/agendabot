<?php

use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/{tenantSlug}', [WebhookController::class, 'handle'])->middleware('throttle:evolution-webhook')->name('webhook');
Route::post('/asaas/webhook', [AsaasWebhookController::class, 'handle'])->middleware('throttle:40,1')->name('asaas.webhook');
