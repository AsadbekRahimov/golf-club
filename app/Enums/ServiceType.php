<?php

namespace App\Enums;

enum ServiceType: string
{
    case LOCKER = 'locker';
    case TRAINING = 'training';

    public function label(): string
    {
        return match($this) {
            self::LOCKER => 'Аренда шкафа',
            self::TRAINING => 'Бронь на тренировку',
        };
    }
}
