<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case PAYMENT_REQUIRED = 'payment_required';
    case PAYMENT_SENT = 'payment_sent';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Ожидает рассмотрения',
            self::PAYMENT_REQUIRED => 'Требуется оплата',
            self::PAYMENT_SENT => 'Чек отправлен',
            self::APPROVED => 'Одобрено',
            self::REJECTED => 'Отклонено',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::PAYMENT_REQUIRED => 'info',
            self::PAYMENT_SENT => 'primary',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
