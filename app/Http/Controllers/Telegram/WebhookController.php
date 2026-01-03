<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Telegram\Bot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function handle(Request $request, Bot $bot): Response
    {
        try {
            $bot->handleUpdate($request->all());
        } catch (\Exception $e) {
            report($e);
        }

        return response('OK', 200);
    }
}
