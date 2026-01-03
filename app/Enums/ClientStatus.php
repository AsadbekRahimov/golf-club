<?php

namespace App\Enums;

enum ClientStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case BLOCKED = 'blocked';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Ожидает подтверждения',
            self::APPROVED => 'Подтвержден',
            self::BLOCKED => 'Заблокирован',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::BLOCKED => 'danger',
        };
    }
}
