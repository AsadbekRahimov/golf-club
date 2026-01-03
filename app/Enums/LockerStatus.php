<?php

namespace App\Enums;

enum LockerStatus: string
{
    case AVAILABLE = 'available';
    case OCCUPIED = 'occupied';

    public function label(): string
    {
        return match($this) {
            self::AVAILABLE => 'Свободен',
            self::OCCUPIED => 'Занят',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::AVAILABLE => 'success',
            self::OCCUPIED => 'danger',
        };
    }
}
