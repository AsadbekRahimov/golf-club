<?php

namespace App\Helpers;

class PaymentMode
{
    public static function isWithPayment(): bool
    {
        return config('golfclub.payment_mode') === 'with_payment';
    }

    public static function isWithoutPayment(): bool
    {
        return config('golfclub.payment_mode') === 'without_payment';
    }
}
