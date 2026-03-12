<?php

namespace App\Enums;

enum SubscriptionType: string
{
    case LOCKER = 'locker';
    case TRAINING = 'training';

    public function label(): string
    {
        return match($this) {
            self::LOCKER => 'Аренда шкафа',
            self::TRAINING => 'Тренировка',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::LOCKER => '🗄️',
            self::TRAINING => '🏌️',
        };
    }
}
