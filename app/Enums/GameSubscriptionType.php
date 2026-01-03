<?php

namespace App\Enums;

enum GameSubscriptionType: string
{
    case ONCE = 'once';
    case MONTHLY = 'monthly';

    public function label(): string
    {
        return match($this) {
            self::ONCE => 'Единоразовая',
            self::MONTHLY => 'Ежемесячная',
        };
    }
}
