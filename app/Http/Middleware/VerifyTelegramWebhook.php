<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secretToken = config('telegram.webhook_secret');

        if (empty($secretToken)) {
            abort(403, 'Webhook secret not configured');
        }

        $headerToken = $request->header('X-Telegram-Bot-Api-Secret-Token') ?? '';

        if (!hash_equals($secretToken, $headerToken)) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}
