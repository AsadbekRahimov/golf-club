<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Subscription extends Model
{
    use HasFactory, AsSource, Filterable;

    protected $fillable = [
        'client_id',
        'booking_request_id',
        'subscription_type',
        'locker_id',
        'start_date',
        'end_date',
        'price',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'subscription_type' => SubscriptionType::class,
        'status' => SubscriptionStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'price' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    protected $allowedFilters = [
        'subscription_type',
        'status',
        'client_id',
    ];

    protected $allowedSorts = [
        'id',
        'start_date',
        'end_date',
        'status',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', SubscriptionStatus::ACTIVE);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', SubscriptionStatus::EXPIRED);
    }

    public function scopeExpiring($query, int $days = 3)
    {
        return $query->active()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [
                now()->toDateString(),
                now()->addDays($days)->toDateString(),
            ]);
    }

    public function scopeOfType($query, SubscriptionType $type)
    {
        return $query->where('subscription_type', $type);
    }

    public function scopeGameSubscriptions($query)
    {
        return $query->whereIn('subscription_type', [
            SubscriptionType::GAME_ONCE,
            SubscriptionType::GAME_MONTHLY,
        ]);
    }

    public function scopeLockerSubscriptions($query)
    {
        return $query->where('subscription_type', SubscriptionType::LOCKER);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(Locker::class);
    }

    public function bookingRequest(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date || !$this->isActive()) {
            return null;
        }

        return max(0, now()->diffInDays($this->end_date, false));
    }

    public function getIsExpiringAttribute(): bool
    {
        return $this->days_remaining !== null && $this->days_remaining <= 3;
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE;
    }

    public function isLocker(): bool
    {
        return $this->subscription_type === SubscriptionType::LOCKER;
    }

    public function isGame(): bool
    {
        return in_array($this->subscription_type, [
            SubscriptionType::GAME_ONCE,
            SubscriptionType::GAME_MONTHLY,
        ]);
    }

    public function expire(): void
    {
        $this->update(['status' => SubscriptionStatus::EXPIRED]);

        if ($this->locker_id) {
            $this->locker->release();
        }
    }

    public function cancel(User $admin, string $reason = null): void
    {
        $this->update([
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $admin->id,
            'cancellation_reason' => $reason,
        ]);

        if ($this->locker_id) {
            $this->locker->release();
        }
    }
}
