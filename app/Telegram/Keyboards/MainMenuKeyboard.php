<?php

namespace App\Telegram\Keyboards;

class MainMenuKeyboard
{
    public static function make(): string
    {
        return json_encode([
            'keyboard' => [
                [
                    ['text' => '📋 Мои подписки'],
                    ['text' => '🎯 Забронировать'],
                ],
                [
                    ['text' => '👤 Профиль'],
                    ['text' => '📞 Связаться'],
                ],
            ],
            'resize_keyboard' => true,
        ]);
    }
}
