<?php

namespace App\Enums;

enum SubscriptionType: string
{
    case GAME_ONCE = 'game_once';
    case GAME_MONTHLY = 'game_monthly';
    case LOCKER = 'locker';

    public function label(): string
    {
        return match($this) {
            self::GAME_ONCE => 'Единоразовая игра',
            self::GAME_MONTHLY => 'Ежемесячная подписка',
            self::LOCKER => 'Аренда шкафа',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::GAME_ONCE => '🏌️',
            self::GAME_MONTHLY => '🏌️‍♂️',
            self::LOCKER => '🗄️',
        };
    }
}
