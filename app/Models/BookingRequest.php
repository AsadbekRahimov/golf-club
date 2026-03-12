<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Metrics\Chartable;
use Orchid\Screen\AsSource;

class BookingRequest extends Model
{
    use HasFactory, AsSource, Filterable, Chartable;

    protected $fillable = [
        'client_id',
        'service_type',
        'locker_duration_months',
        'locker_start_date',
        'status',
        'admin_notes',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'service_type' => ServiceType::class,
        'status' => BookingStatus::class,
        'locker_duration_months' => 'integer',
        'locker_start_date' => 'date',
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
        'status',
    ];

    public function scopePending($query)
    {
        return $query->where('status', BookingStatus::PENDING);
    }

    public function scopeAwaitingAction($query)
    {
        return $query->where('status', BookingStatus::PENDING);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isLocker(): bool
    {
        return $this->service_type === ServiceType::LOCKER;
    }

    public function isTraining(): bool
    {
        return $this->service_type === ServiceType::TRAINING;
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

    public function isApproved(): bool
    {
        return $this->status === BookingStatus::APPROVED;
    }
}
