<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Активна',
            self::EXPIRED => 'Истекла',
            self::CANCELLED => 'Отменена',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::EXPIRED => 'secondary',
            self::CANCELLED => 'danger',
        };
    }
}
