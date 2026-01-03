<?php

namespace App\Models;

use App\Enums\LockerStatus;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Locker extends Model
{
    use HasFactory, AsSource, Filterable;

    protected $fillable = [
        'locker_number',
        'status',
        'description',
    ];

    protected $casts = [
        'status' => LockerStatus::class,
    ];

    protected $allowedFilters = [
        'locker_number',
        'status',
    ];

    protected $allowedSorts = [
        'id',
        'locker_number',
        'status',
    ];

    public function scopeAvailable($query)
    {
        return $query->where('status', LockerStatus::AVAILABLE);
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', LockerStatus::OCCUPIED);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->latest();
    }

    public function currentSubscription(): HasOne
    {
        return $this->activeSubscription();
    }

    public function isAvailable(): bool
    {
        return $this->status === LockerStatus::AVAILABLE;
    }

    public function occupy(): void
    {
        $this->update(['status' => LockerStatus::OCCUPIED]);
    }

    public function release(): void
    {
        $this->update(['status' => LockerStatus::AVAILABLE]);
    }

    public static function getFirstAvailable(): ?self
    {
        return self::available()
            ->orderBy('locker_number')
            ->first();
    }

    public static function availableCount(): int
    {
        return self::available()->count();
    }
}
