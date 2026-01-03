# Этап 1: База данных - Миграции и Модели

## Обзор этапа

**Цель:** Создать структуру базы данных и Eloquent модели для всей системы.

**Длительность:** 2-3 дня

**Результат:** Готовая схема БД с моделями, связями и seeders.

---

## Чек-лист задач

- [ ] Создать миграции для всех таблиц
- [ ] Создать Enum классы для статусов
- [ ] Создать Eloquent модели
- [ ] Настроить связи между моделями
- [ ] Создать Seeders для начальных данных
- [ ] Создать Factories для тестов
- [ ] Протестировать миграции и связи

---

## 1. Создание Enum классов

### 1.1 ClientStatus

**Файл:** `app/Enums/ClientStatus.php`

```php
<?php

namespace App\Enums;

enum ClientStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case BLOCKED = 'blocked';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Ожидает подтверждения',
            self::APPROVED => 'Подтвержден',
            self::BLOCKED => 'Заблокирован',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::BLOCKED => 'danger',
        };
    }
}
```

### 1.2 LockerStatus

**Файл:** `app/Enums/LockerStatus.php`

```php
<?php

namespace App\Enums;

enum LockerStatus: string
{
    case AVAILABLE = 'available';
    case OCCUPIED = 'occupied';

    public function label(): string
    {
        return match($this) {
            self::AVAILABLE => 'Свободен',
            self::OCCUPIED => 'Занят',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::AVAILABLE => 'success',
            self::OCCUPIED => 'danger',
        };
    }
}
```

### 1.3 SubscriptionType

**Файл:** `app/Enums/SubscriptionType.php`

```php
<?php

namespace App\Enums;

enum SubscriptionType: string
{
    case GAME_ONCE = 'game_once';
    case GAME_MONTHLY = 'game_monthly';
    case LOCKER = 'locker';

    public function label(): string
    {
        return match($this) {
            self::GAME_ONCE => 'Единоразовая игра',
            self::GAME_MONTHLY => 'Ежемесячная подписка',
            self::LOCKER => 'Аренда шкафа',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::GAME_ONCE => '🏌️',
            self::GAME_MONTHLY => '🏌️‍♂️',
            self::LOCKER => '🗄️',
        };
    }
}
```

### 1.4 SubscriptionStatus

**Файл:** `app/Enums/SubscriptionStatus.php`

```php
<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Активна',
            self::EXPIRED => 'Истекла',
            self::CANCELLED => 'Отменена',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::EXPIRED => 'secondary',
            self::CANCELLED => 'danger',
        };
    }
}
```

### 1.5 ServiceType

**Файл:** `app/Enums/ServiceType.php`

```php
<?php

namespace App\Enums;

enum ServiceType: string
{
    case GAME = 'game';
    case LOCKER = 'locker';
    case BOTH = 'both';

    public function label(): string
    {
        return match($this) {
            self::GAME => 'Подписка на игру',
            self::LOCKER => 'Аренда шкафа',
            self::BOTH => 'Комплексный пакет',
        };
    }
}
```

### 1.6 GameSubscriptionType

**Файл:** `app/Enums/GameSubscriptionType.php`

```php
<?php

namespace App\Enums;

enum GameSubscriptionType: string
{
    case ONCE = 'once';
    case MONTHLY = 'monthly';

    public function label(): string
    {
        return match($this) {
            self::ONCE => 'Единоразовая',
            self::MONTHLY => 'Ежемесячная',
        };
    }
}
```

### 1.7 BookingStatus

**Файл:** `app/Enums/BookingStatus.php`

```php
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
```

### 1.8 PaymentStatus

**Файл:** `app/Enums/PaymentStatus.php`

```php
<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Ожидает проверки',
            self::VERIFIED => 'Подтверждено',
            self::REJECTED => 'Отклонено',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::VERIFIED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
```

---

## 2. Создание миграций

### 2.1 Миграция: clients

**Команда:** `php artisan make:migration create_clients_table`

**Файл:** `database/migrations/XXXX_XX_XX_XXXXXX_create_clients_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            
            // Контактные данные
            $table->string('phone_number', 20)->unique();
            $table->bigInteger('telegram_id')->unique();
            $table->bigInteger('telegram_chat_id');
            
            // Данные из Telegram
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            
            // Данные от администратора
            $table->string('full_name')->nullable();
            
            // Статус
            $table->string('status', 20)->default('pending');
            
            // Подтверждение
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            // Отклонение
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Дополнительно
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Индексы
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
```

### 2.2 Миграция: lockers

**Команда:** `php artisan make:migration create_lockers_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lockers', function (Blueprint $table) {
            $table->id();
            $table->string('locker_number', 10)->unique();
            $table->string('status', 20)->default('available');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lockers');
    }
};
```

### 2.3 Миграция: booking_requests

**Команда:** `php artisan make:migration create_booking_requests_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            
            // Клиент
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->cascadeOnDelete();
            
            // Услуги
            $table->string('service_type', 20); // game, locker, both
            $table->string('game_subscription_type', 20)->nullable(); // once, monthly
            $table->unsignedInteger('locker_duration_months')->nullable();
            
            // Стоимость
            $table->decimal('total_price', 10, 2);
            
            // Статус
            $table->string('status', 30)->default('pending');
            
            // Заметки
            $table->text('admin_notes')->nullable();
            
            // Обработка
            $table->foreignId('processed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
            
            // Индексы
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
```

### 2.4 Миграция: payments

**Команда:** `php artisan make:migration create_payments_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // Связи
            $table->foreignId('booking_request_id')
                  ->unique()
                  ->constrained('booking_requests')
                  ->cascadeOnDelete();
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->cascadeOnDelete();
            
            // Сумма
            $table->decimal('amount', 10, 2);
            
            // Файл чека
            $table->string('receipt_file_path', 500)->nullable();
            $table->string('receipt_file_name', 255)->nullable();
            $table->string('receipt_file_type', 50)->nullable();
            
            // Статус
            $table->string('status', 20)->default('pending');
            
            // Проверка
            $table->foreignId('verified_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            
            // Индексы
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

### 2.5 Миграция: subscriptions

**Команда:** `php artisan make:migration create_subscriptions_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            
            // Связи
            $table->foreignId('client_id')
                  ->constrained('clients')
                  ->cascadeOnDelete();
            $table->foreignId('booking_request_id')
                  ->nullable()
                  ->constrained('booking_requests')
                  ->nullOnDelete();
            
            // Тип подписки
            $table->string('subscription_type', 20);
            
            // Шкаф (только для locker)
            $table->foreignId('locker_id')
                  ->nullable()
                  ->constrained('lockers')
                  ->nullOnDelete();
            
            // Период
            $table->date('start_date');
            $table->date('end_date')->nullable();
            
            // Стоимость
            $table->decimal('price', 10, 2);
            
            // Статус
            $table->string('status', 20)->default('active');
            
            // Отмена
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
            
            // Индексы
            $table->index('subscription_type');
            $table->index('status');
            $table->index('end_date');
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
```

### 2.6 Миграция: settings

**Команда:** `php artisan make:migration create_settings_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string');
            $table->string('group', 100)->default('general');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

---

## 3. Создание моделей

### 3.1 Model: Client

**Файл:** `app/Models/Client.php`

```php
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
        'telegram_id' => 'integer',
        'telegram_chat_id' => 'integer',
        'status' => ClientStatus::class,
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $allowedFilters = [
        'phone_number',
        'status',
        'created_at',
    ];

    protected $allowedSorts = [
        'id',
        'phone_number',
        'created_at',
        'status',
    ];

    // ==================== Accessors ====================

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

    // ==================== Scopes ====================

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

    // ==================== Relations ====================

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

    // ==================== Methods ====================

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
```

### 3.2 Model: Locker

**Файл:** `app/Models/Locker.php`

```php
<?php

namespace App\Models;

use App\Enums\LockerStatus;
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

    // ==================== Scopes ====================

    public function scopeAvailable($query)
    {
        return $query->where('status', LockerStatus::AVAILABLE);
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', LockerStatus::OCCUPIED);
    }

    // ==================== Relations ====================

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest();
    }

    // ==================== Methods ====================

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
```

### 3.3 Model: BookingRequest

**Файл:** `app/Models/BookingRequest.php`

```php
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
        'status',
        'service_type',
        'created_at',
    ];

    protected $allowedSorts = [
        'id',
        'created_at',
        'total_price',
        'status',
    ];

    // ==================== Scopes ====================

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

    // ==================== Relations ====================

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

    // ==================== Methods ====================

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
```

### 3.4 Model: Payment

**Файл:** `app/Models/Payment.php`

```php
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

    // ==================== Scopes ====================

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', PaymentStatus::VERIFIED);
    }

    // ==================== Relations ====================

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

    // ==================== Accessors ====================

    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_file_path) {
            return null;
        }

        return Storage::url($this->receipt_file_path);
    }

    public function getHasReceiptAttribute(): bool
    {
        return !empty($this->receipt_file_path);
    }

    // ==================== Methods ====================

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
```

### 3.5 Model: Subscription

**Файл:** `app/Models/Subscription.php`

```php
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

    // ==================== Scopes ====================

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

    // ==================== Relations ====================

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

    // ==================== Accessors ====================

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

    // ==================== Methods ====================

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
```

### 3.6 Model: Setting

**Файл:** `app/Models/Setting.php`

```php
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

    // ==================== Static Methods ====================

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

    // ==================== Helper Methods ====================

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
```

---

## 4. Создание Seeders

### 4.1 SettingsSeeder

**Файл:** `database/seeders/SettingsSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Payment settings
            [
                'key' => 'payment_card_number',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Номер карты для приема платежей',
            ],
            [
                'key' => 'payment_card_holder',
                'value' => null,
                'type' => 'string',
                'group' => 'payment',
                'description' => 'Имя владельца карты',
            ],
            
            // Contact settings
            [
                'key' => 'contact_phone',
                'value' => null,
                'type' => 'string',
                'group' => 'contact',
                'description' => 'Контактный телефон администрации',
            ],
            
            // Pricing settings
            [
                'key' => 'game_once_price',
                'value' => '50.00',
                'type' => 'decimal',
                'group' => 'pricing',
                'description' => 'Стоимость единоразовой игры ($)',
            ],
            [
                'key' => 'game_monthly_price',
                'value' => '200.00',
                'type' => 'decimal',
                'group' => 'pricing',
                'description' => 'Стоимость месячной подписки ($)',
            ],
            [
                'key' => 'locker_monthly_price',
                'value' => '10.00',
                'type' => 'decimal',
                'group' => 'pricing',
                'description' => 'Стоимость аренды шкафа в месяц ($)',
            ],
            
            // Notification settings
            [
                'key' => 'notification_days_before',
                'value' => '3',
                'type' => 'integer',
                'group' => 'notifications',
                'description' => 'За сколько дней уведомлять об истечении подписки',
            ],
            
            // Messages
            [
                'key' => 'welcome_message',
                'value' => 'Добро пожаловать в гольф-клуб! Ваша заявка на регистрацию отправлена.',
                'type' => 'text',
                'group' => 'messages',
                'description' => 'Приветственное сообщение для новых клиентов',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
```

### 4.2 LockersSeeder

**Файл:** `database/seeders/LockersSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Locker;
use App\Enums\LockerStatus;
use Illuminate\Database\Seeder;

class LockersSeeder extends Seeder
{
    public function run(): void
    {
        $totalLockers = 50;

        for ($i = 1; $i <= $totalLockers; $i++) {
            Locker::updateOrCreate(
                ['locker_number' => str_pad($i, 3, '0', STR_PAD_LEFT)],
                ['status' => LockerStatus::AVAILABLE]
            );
        }
    }
}
```

### 4.3 AdminSeeder

**Файл:** `database/seeders/AdminSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@golfclub.local'],
            [
                'name' => 'Администратор',
                'password' => Hash::make('password'),
                'permissions' => [
                    'platform.index' => true,
                    'platform.systems.roles' => true,
                    'platform.systems.users' => true,
                ],
            ]
        );
    }
}
```

### 4.4 DatabaseSeeder

**Файл:** `database/seeders/DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            SettingsSeeder::class,
            LockersSeeder::class,
        ]);
    }
}
```

---

## 5. Команды для выполнения

### 5.1 Последовательность команд

```bash
# 1. Создать директорию для Enums
mkdir app/Enums

# 2. Создать Enum файлы (вручную или через IDE)

# 3. Создать миграции
php artisan make:migration create_clients_table
php artisan make:migration create_lockers_table
php artisan make:migration create_booking_requests_table
php artisan make:migration create_payments_table
php artisan make:migration create_subscriptions_table
php artisan make:migration create_settings_table

# 4. Создать модели
php artisan make:model Client
php artisan make:model Locker
php artisan make:model BookingRequest
php artisan make:model Payment
php artisan make:model Subscription
php artisan make:model Setting

# 5. Создать seeders
php artisan make:seeder SettingsSeeder
php artisan make:seeder LockersSeeder
php artisan make:seeder AdminSeeder

# 6. Запустить миграции
php artisan migrate

# 7. Запустить seeders
php artisan db:seed

# 8. Проверить таблицы
php artisan tinker
>>> \App\Models\Client::count()
>>> \App\Models\Locker::count()
>>> \App\Models\Setting::all()
```

---

## 6. Тестирование

### 6.1 Проверка связей

```php
// В tinker или в тестах

// Создать тестового клиента
$client = \App\Models\Client::create([
    'phone_number' => '+998 90 123-45-67',
    'telegram_id' => 123456789,
    'telegram_chat_id' => 123456789,
    'first_name' => 'Тест',
    'status' => 'approved',
]);

// Проверить что создался
$client->fresh();
$client->display_name; // "Тест"
$client->isApproved(); // true

// Проверить шкафы
\App\Models\Locker::availableCount(); // 50
\App\Models\Locker::getFirstAvailable()->locker_number; // "001"

// Проверить настройки
\App\Models\Setting::getLockerMonthlyPrice(); // 10.0
\App\Models\Setting::getGameOncePrice(); // 50.0
```

---

## 7. Критерии завершения этапа

- [ ] Все миграции созданы и успешно выполняются
- [ ] Все Enum классы созданы
- [ ] Все модели созданы с правильными связями
- [ ] Seeders создают начальные данные
- [ ] Можно создавать/читать/обновлять записи через модели
- [ ] Связи между моделями работают корректно
