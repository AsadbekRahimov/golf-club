<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Orchid\Filters\Filterable;
use Orchid\Screen\AsSource;

class Payment extends Model
{
    use HasFactory, AsSource, Filterable;

    protected $fillable = [
        'booking_request_id',
        'client_id',
        'amount',
        'receipt_file_path',
        'receipt_file_name',
        'receipt_file_type',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => PaymentStatus::class,
        'verified_at' => 'datetime',
    ];

    protected $allowedFilters = [
        'status',
        'created_at',
    ];

    protected $allowedSorts = [
        'id',
        'created_at',
        'amount',
        'status',
    ];

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', PaymentStatus::VERIFIED);
    }

    public function bookingRequest(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->receipt_file_path);
    }

    public function getHasReceiptAttribute(): bool
    {
        return !empty($this->receipt_file_path);
    }

    public function getIsImageReceiptAttribute(): bool
    {
        return str_starts_with($this->receipt_file_type ?? '', 'image/');
    }

    public function getIsPdfReceiptAttribute(): bool
    {
        return $this->receipt_file_type === 'application/pdf';
    }

    public function verify(User $admin): void
    {
        $this->update([
            'status' => PaymentStatus::VERIFIED,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
    }

    public function reject(User $admin, string $reason = null): void
    {
        $this->update([
            'status' => PaymentStatus::REJECTED,
            'verified_by' => $admin->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isVerified(): bool
    {
        return $this->status === PaymentStatus::VERIFIED;
    }
}
