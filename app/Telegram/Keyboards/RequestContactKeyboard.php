<?php

namespace App\Telegram\Keyboards;

class RequestContactKeyboard
{
    public static function make(): string
    {
        return json_encode([
            'keyboard' => [
                [
                    [
                        'text' => '📱 Поделиться номером телефона',
                        'request_contact' => true,
                    ],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }
}
