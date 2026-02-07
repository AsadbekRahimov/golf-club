<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class BookingRequest extends Model
{
    use HasFactory, AsSource, Filterable;

    protected $fillable = [
        'client_id',
        'service_type',
        'game_subscription_type',
        'locker_duration_months',
        'total_price',
        'status',
        'admin_notes',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'service_type' => ServiceType::class,
        'game_subscription_type' => GameSubscriptionType::class,
        'status' => BookingStatus::class,
        'total_price' => 'decimal:2',
        'locker_duration_months' => 'integer',
        'processed_at' => 'datetime',
    ];

    protected $allowedFilters = [
        'status' => \Orchid\Filters\Types\Where::class,
        'service_type' => \Orchid\Filters\Types\Where::class,
        'created_at' => \Orchid\Filters\Types\WhereDate::class,
    ];

    protected $allowedSorts = [
        'id',
        'created_at',
        'total_price',
        'status',
    ];

    public function scopePending($query)
    {
        return $query->where('status', BookingStatus::PENDING);
    }

    public function scopePaymentRequired($query)
    {
        return $query->where('status', BookingStatus::PAYMENT_REQUIRED);
    }

    public function scopePaymentSent($query)
    {
        return $query->where('status', BookingStatus::PAYMENT_SENT);
    }

    public function scopeAwaitingAction($query)
    {
        return $query->whereIn('status', [
            BookingStatus::PENDING,
            BookingStatus::PAYMENT_SENT,
        ]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function hasGame(): bool
    {
        return in_array($this->service_type, [
            ServiceType::GAME,
            ServiceType::BOTH,
        ]);
    }

    public function hasLocker(): bool
    {
        return in_array($this->service_type, [
            ServiceType::LOCKER,
            ServiceType::BOTH,
        ]);
    }

    public function requirePayment(User $admin): void
    {
        $this->update([
            'status' => BookingStatus::PAYMENT_REQUIRED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
    }

    public function markPaymentSent(): void
    {
        $this->update(['status' => BookingStatus::PAYMENT_SENT]);
    }

    public function approve(User $admin): void
    {
        $this->update([
            'status' => BookingStatus::APPROVED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
    }

    public function reject(User $admin, string $reason = null): void
    {
        $this->update([
            'status' => BookingStatus::REJECTED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'admin_notes' => $reason,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === BookingStatus::PENDING;
    }

    public function isPaymentRequired(): bool
    {
        return $this->status === BookingStatus::PAYMENT_REQUIRED;
    }

    public function isApproved(): bool
    {
        return $this->status === BookingStatus::APPROVED;
    }
}
