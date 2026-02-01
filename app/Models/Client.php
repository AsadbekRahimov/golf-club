<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Client extends Model
{
    use HasFactory, AsSource, Filterable;

    protected $fillable = [
        'phone_number',
        'telegram_id',
        'telegram_chat_id',
        'first_name',
        'last_name',
        'username',
        'full_name',
        'status',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'telegram_id' => 'string',
        'telegram_chat_id' => 'string',
        'status' => ClientStatus::class,
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $allowedFilters = [
        'phone_number' => \Orchid\Filters\Types\Like::class,
        'status' => \Orchid\Filters\Types\Where::class,
        'created_at' => \Orchid\Filters\Types\WhereDate::class,
    ];

    protected $allowedSorts = [
        'id',
        'phone_number',
        'created_at',
        'status',
    ];

    public function getDisplayNameAttribute(): string
    {
        if ($this->full_name) {
            return $this->full_name;
        }

        $name = trim("{$this->first_name} {$this->last_name}");
        
        return $name ?: $this->username ?: $this->phone_number;
    }

    public function getTelegramLinkAttribute(): ?string
    {
        return $this->username 
            ? "https://t.me/{$this->username}" 
            : null;
    }

    public function scopePending($query)
    {
        return $query->where('status', ClientStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ClientStatus::APPROVED);
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', ClientStatus::BLOCKED);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('status', 'active');
    }

    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function approve(User $admin): void
    {
        $this->update([
            'status' => ClientStatus::APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(string $reason = null): void
    {
        $this->update([
            'status' => ClientStatus::BLOCKED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function isApproved(): bool
    {
        return $this->status === ClientStatus::APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === ClientStatus::PENDING;
    }

    public function isBlocked(): bool
    {
        return $this->status === ClientStatus::BLOCKED;
    }
}
