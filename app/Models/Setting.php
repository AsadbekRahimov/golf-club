<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Orchid\Screen\AsSource;

class Setting extends Model
{
    use HasFactory, AsSource;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    public static function setValue(string $key, mixed $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );

        Cache::forget("setting.{$key}");
    }

    public static function getByGroup(string $group): array
    {
        return self::where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }

    protected static function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'decimal', 'float' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public static function getPaymentCardNumber(): ?string
    {
        return self::getValue('payment_card_number');
    }

    public static function getContactPhone(): ?string
    {
        return self::getValue('contact_phone');
    }

    public static function getGameOncePrice(): float
    {
        return (float) self::getValue('game_once_price', 0);
    }

    public static function getGameMonthlyPrice(): float
    {
        return (float) self::getValue('game_monthly_price', 0);
    }

    public static function getLockerMonthlyPrice(): float
    {
        return (float) self::getValue('locker_monthly_price', 10);
    }

    public static function getNotificationDaysBefore(): int
    {
        return (int) self::getValue('notification_days_before', 3);
    }
}
