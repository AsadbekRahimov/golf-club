<?php

use App\Http\Controllers\Telegram\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Telegram Webhook
Route::post('/telegram/webhook', [WebhookController::class, 'handle'])
    ->middleware('telegram.webhook')
    ->name('telegram.webhook');
