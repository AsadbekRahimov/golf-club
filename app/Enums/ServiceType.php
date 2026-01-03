<?php

namespace App\Enums;

enum ServiceType: string
{
    case GAME = 'game';
    case LOCKER = 'locker';
    case BOTH = 'both';

    public function label(): string
    {
        return match($this) {
            self::GAME => 'Подписка на игру',
            self::LOCKER => 'Аренда шкафа',
            self::BOTH => 'Комплексный пакет',
        };
    }
}
