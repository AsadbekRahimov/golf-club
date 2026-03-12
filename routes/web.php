<?php

use App\Http\Controllers\Telegram\WebhookController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect('/admin')
        : redirect('/admin/login');
});

// Telegram Webhook
Route::post('/telegram/webhook', [WebhookController::class, 'handle'])
    ->middleware('telegram.webhook')
    ->name('telegram.webhook');
