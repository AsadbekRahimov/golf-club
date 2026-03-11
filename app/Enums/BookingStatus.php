<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Ожидает рассмотрения',
            self::APPROVED => 'Одобрено',
            self::REJECTED => 'Отклонено',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
