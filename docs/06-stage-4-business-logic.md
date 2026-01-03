# Этап 4: Бизнес-логика

## Обзор этапа

**Цель:** Реализовать все сервисы бизнес-логики, связывающие админ-панель и Telegram-бота.

**Длительность:** 4-5 дней

**Зависимости:** Этапы 2, 3 (Админ-панель и Telegram бот)

**Результат:** Полностью работающая бизнес-логика системы.

---

## Чек-лист задач

- [ ] Создать ClientService
- [ ] Создать BookingService
- [ ] Создать PaymentService
- [ ] Создать SubscriptionService
- [ ] Создать LockerService
- [ ] Создать NotificationService
- [ ] Настроить Events и Listeners
- [ ] Протестировать все бизнес-сценарии

---

## 1. ClientService

**Файл:** `app/Services/ClientService.php`

```php
<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Events\ClientApproved;
use App\Events\ClientRegistered;
use App\Events\ClientRejected;
use App\Models\Client;
use App\Models\User;

class ClientService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Регистрация нового клиента
     */
    public function register(array $data): Client
    {
        $client = Client::create([
            'phone_number' => $data['phone_number'],
            'telegram_id' => $data['telegram_id'],
            'telegram_chat_id' => $data['telegram_chat_id'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'username' => $data['username'] ?? null,
            'status' => ClientStatus::PENDING,
        ]);

        event(new ClientRegistered($client));

        return $client;
    }

    /**
     * Подтверждение клиента
     */
    public function approve(Client $client, User $admin): Client
    {
        $client->update([
            'status' => ClientStatus::APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        event(new ClientApproved($client, $admin));

        return $client->fresh();
    }

    /**
     * Отклонение клиента
     */
    public function reject(Client $client, ?string $reason = null): Client
    {
        $client->update([
            'status' => ClientStatus::BLOCKED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        event(new ClientRejected($client, $reason));

        return $client->fresh();
    }

    /**
     * Блокировка клиента
     */
    public function block(Client $client, ?string $reason = null): Client
    {
        $client->update([
            'status' => ClientStatus::BLOCKED,
            'notes' => $reason ? ($client->notes . "\nЗаблокирован: " . $reason) : $client->notes,
        ]);

        return $client->fresh();
    }

    /**
     * Разблокировка клиента
     */
    public function unblock(Client $client): Client
    {
        $client->update([
            'status' => ClientStatus::APPROVED,
        ]);

        return $client->fresh();
    }

    /**
     * Поиск клиента по номеру телефона
     */
    public function findByPhone(string $phone): ?Client
    {
        return Client::where('phone_number', $this->normalizePhone($phone))->first();
    }

    /**
     * Поиск клиента по Telegram ID
     */
    public function findByTelegramId(int $telegramId): ?Client
    {
        return Client::where('telegram_id', $telegramId)->first();
    }

    /**
     * Нормализация номера телефона
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        if (preg_match('/^\+?998(\d{2})(\d{3})(\d{2})(\d{2})$/', $phone, $matches)) {
            return "+998 {$matches[1]} {$matches[2]}-{$matches[3]}-{$matches[4]}";
        }

        return $phone;
    }

    /**
     * Валидация номера телефона
     */
    public function isValidPhone(string $phone): bool
    {
        $normalized = $this->normalizePhone($phone);
        return (bool) preg_match('/^\+998\s\d{2}\s\d{3}-\d{2}-\d{2}$/', $normalized);
    }

    /**
     * Получить статистику клиентов
     */
    public function getStatistics(): array
    {
        return [
            'total' => Client::count(),
            'pending' => Client::pending()->count(),
            'approved' => Client::approved()->count(),
            'blocked' => Client::blocked()->count(),
            'with_active_subscriptions' => Client::whereHas('activeSubscriptions')->count(),
        ];
    }
}
```

---

## 2. BookingService

**Файл:** `app/Services/BookingService.php`

```php
<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Enums\SubscriptionType;
use App\Events\BookingApproved;
use App\Events\BookingCreated;
use App\Events\BookingRejected;
use App\Events\PaymentRequested;
use App\Events\PaymentVerified;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;

class BookingService
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected LockerService $lockerService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Создание запроса на бронирование
     */
    public function createBooking(
        Client $client,
        ServiceType $serviceType,
        ?GameSubscriptionType $gameType = null,
        ?int $lockerMonths = null
    ): BookingRequest {
        // Проверяем доступность шкафов если нужно
        if (in_array($serviceType, [ServiceType::LOCKER, ServiceType::BOTH])) {
            if (!$this->lockerService->hasAvailable()) {
                throw new \Exception('Нет свободных шкафов');
            }
        }

        // Рассчитываем стоимость
        $price = $this->calculatePrice($serviceType, $gameType, $lockerMonths);

        $booking = BookingRequest::create([
            'client_id' => $client->id,
            'service_type' => $serviceType,
            'game_subscription_type' => $gameType,
            'locker_duration_months' => $lockerMonths,
            'total_price' => $price,
            'status' => BookingStatus::PENDING,
        ]);

        event(new BookingCreated($booking));

        return $booking;
    }

    /**
     * Расчет стоимости
     */
    public function calculatePrice(
        ServiceType $serviceType,
        ?GameSubscriptionType $gameType = null,
        ?int $lockerMonths = null
    ): float {
        $price = 0;

        // Стоимость игры
        if (in_array($serviceType, [ServiceType::GAME, ServiceType::BOTH])) {
            $price += match ($gameType) {
                GameSubscriptionType::ONCE => Setting::getGameOncePrice(),
                GameSubscriptionType::MONTHLY => Setting::getGameMonthlyPrice(),
                default => 0,
            };
        }

        // Стоимость шкафа
        if (in_array($serviceType, [ServiceType::LOCKER, ServiceType::BOTH])) {
            $price += Setting::getLockerMonthlyPrice() * ($lockerMonths ?? 1);
        }

        return $price;
    }

    /**
     * Подтверждение без оплаты
     */
    public function approveWithoutPayment(BookingRequest $booking, User $admin): void
    {
        $booking->update([
            'status' => BookingStatus::APPROVED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        // Активируем подписки
        $this->activateSubscriptions($booking);

        event(new BookingApproved($booking, false));
    }

    /**
     * Запрос оплаты
     */
    public function requirePayment(BookingRequest $booking, User $admin): void
    {
        $booking->update([
            'status' => BookingStatus::PAYMENT_REQUIRED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        // Создаем запись платежа
        Payment::create([
            'booking_request_id' => $booking->id,
            'client_id' => $booking->client_id,
            'amount' => $booking->total_price,
            'status' => 'pending',
        ]);

        event(new PaymentRequested($booking));
    }

    /**
     * Подтверждение оплаты
     */
    public function verifyPayment(Payment $payment, User $admin): void
    {
        $payment->verify($admin);

        $booking = $payment->bookingRequest;
        $booking->approve($admin);

        // Активируем подписки
        $this->activateSubscriptions($booking);

        event(new PaymentVerified($payment));
        event(new BookingApproved($booking, true));
    }

    /**
     * Отклонение запроса
     */
    public function reject(BookingRequest $booking, User $admin, ?string $reason = null): void
    {
        $booking->reject($admin, $reason);

        event(new BookingRejected($booking, $reason));
    }

    /**
     * Активация подписок по запросу
     */
    protected function activateSubscriptions(BookingRequest $booking): void
    {
        $client = $booking->client;

        // Подписка на игру
        if ($booking->hasGame()) {
            $subscriptionType = $booking->game_subscription_type === GameSubscriptionType::ONCE
                ? SubscriptionType::GAME_ONCE
                : SubscriptionType::GAME_MONTHLY;

            $endDate = $subscriptionType === SubscriptionType::GAME_MONTHLY
                ? now()->addMonth()
                : null;

            $price = $booking->game_subscription_type === GameSubscriptionType::ONCE
                ? Setting::getGameOncePrice()
                : Setting::getGameMonthlyPrice();

            $this->subscriptionService->create(
                $client,
                $subscriptionType,
                $price,
                $endDate,
                $booking
            );
        }

        // Подписка на шкаф
        if ($booking->hasLocker()) {
            $locker = $this->lockerService->assignToClient($client);
            
            if ($locker) {
                $months = $booking->locker_duration_months ?? 1;
                $price = Setting::getLockerMonthlyPrice() * $months;
                $endDate = now()->addMonths($months);

                $this->subscriptionService->create(
                    $client,
                    SubscriptionType::LOCKER,
                    $price,
                    $endDate,
                    $booking,
                    $locker
                );
            }
        }
    }

    /**
     * Получить статистику бронирований
     */
    public function getStatistics(): array
    {
        return [
            'pending' => BookingRequest::pending()->count(),
            'payment_required' => BookingRequest::paymentRequired()->count(),
            'payment_sent' => BookingRequest::paymentSent()->count(),
            'approved_today' => BookingRequest::where('status', BookingStatus::APPROVED)
                ->whereDate('processed_at', today())
                ->count(),
            'total_revenue_today' => BookingRequest::where('status', BookingStatus::APPROVED)
                ->whereDate('processed_at', today())
                ->sum('total_price'),
        ];
    }
}
```

---

## 3. SubscriptionService

**Файл:** `app/Services/SubscriptionService.php`

```php
<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Events\SubscriptionActivated;
use App\Events\SubscriptionExpired;
use App\Events\SubscriptionExpiring;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SubscriptionService
{
    public function __construct(
        protected LockerService $lockerService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Создание подписки
     */
    public function create(
        Client $client,
        SubscriptionType $type,
        float $price,
        ?Carbon $endDate = null,
        ?BookingRequest $booking = null,
        ?Locker $locker = null
    ): Subscription {
        $subscription = Subscription::create([
            'client_id' => $client->id,
            'booking_request_id' => $booking?->id,
            'subscription_type' => $type,
            'locker_id' => $locker?->id,
            'start_date' => now(),
            'end_date' => $endDate,
            'price' => $price,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        event(new SubscriptionActivated($subscription));

        return $subscription;
    }

    /**
     * Отмена подписки
     */
    public function cancel(Subscription $subscription, User $admin, ?string $reason = null): void
    {
        $subscription->cancel($admin, $reason);

        // Освобождаем шкаф если есть
        if ($subscription->locker_id) {
            $this->lockerService->release($subscription->locker);
        }
    }

    /**
     * Продление подписки
     */
    public function extend(Subscription $subscription, int $months): Subscription
    {
        $newEndDate = $subscription->end_date
            ? $subscription->end_date->addMonths($months)
            : now()->addMonths($months);

        $additionalPrice = $subscription->isLocker()
            ? Setting::getLockerMonthlyPrice() * $months
            : Setting::getGameMonthlyPrice() * $months;

        $subscription->update([
            'end_date' => $newEndDate,
            'price' => $subscription->price + $additionalPrice,
        ]);

        return $subscription->fresh();
    }

    /**
     * Проверка истекающих подписок
     */
    public function checkExpiring(): Collection
    {
        $daysBefore = Setting::getNotificationDaysBefore();

        $expiring = Subscription::with('client')
            ->expiring($daysBefore)
            ->get();

        foreach ($expiring as $subscription) {
            event(new SubscriptionExpiring($subscription));
        }

        return $expiring;
    }

    /**
     * Обработка истекших подписок
     */
    public function processExpired(): int
    {
        $expired = Subscription::active()
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->get();

        $count = 0;

        foreach ($expired as $subscription) {
            $subscription->expire();
            event(new SubscriptionExpired($subscription));
            $count++;
        }

        return $count;
    }

    /**
     * Получить активные подписки клиента
     */
    public function getClientSubscriptions(Client $client): Collection
    {
        return $client->activeSubscriptions()
            ->with('locker')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Проверка наличия активной подписки на игру
     */
    public function hasActiveGameSubscription(Client $client): bool
    {
        return $client->activeSubscriptions()
            ->gameSubscriptions()
            ->exists();
    }

    /**
     * Проверка наличия активной подписки на шкаф
     */
    public function hasActiveLockerSubscription(Client $client): bool
    {
        return $client->activeSubscriptions()
            ->lockerSubscriptions()
            ->exists();
    }

    /**
     * Получить статистику подписок
     */
    public function getStatistics(): array
    {
        return [
            'active_total' => Subscription::active()->count(),
            'active_game_once' => Subscription::active()->ofType(SubscriptionType::GAME_ONCE)->count(),
            'active_game_monthly' => Subscription::active()->ofType(SubscriptionType::GAME_MONTHLY)->count(),
            'active_locker' => Subscription::active()->ofType(SubscriptionType::LOCKER)->count(),
            'expiring_soon' => Subscription::expiring()->count(),
            'expired_today' => Subscription::whereDate('end_date', today())
                ->where('status', SubscriptionStatus::EXPIRED)
                ->count(),
        ];
    }
}
```

---

## 4. LockerService

**Файл:** `app/Services/LockerService.php`

```php
<?php

namespace App\Services;

use App\Enums\LockerStatus;
use App\Models\Client;
use App\Models\Locker;
use Illuminate\Support\Collection;

class LockerService
{
    /**
     * Проверка наличия свободных шкафов
     */
    public function hasAvailable(): bool
    {
        return Locker::available()->exists();
    }

    /**
     * Количество свободных шкафов
     */
    public function availableCount(): int
    {
        return Locker::available()->count();
    }

    /**
     * Получить первый свободный шкаф
     */
    public function getFirstAvailable(): ?Locker
    {
        return Locker::available()
            ->orderBy('locker_number')
            ->first();
    }

    /**
     * Назначить шкаф клиенту
     */
    public function assignToClient(Client $client): ?Locker
    {
        $locker = $this->getFirstAvailable();

        if (!$locker) {
            return null;
        }

        $locker->occupy();

        return $locker;
    }

    /**
     * Освободить шкаф
     */
    public function release(Locker $locker): void
    {
        $locker->release();
    }

    /**
     * Получить все шкафы с информацией
     */
    public function getAllWithInfo(): Collection
    {
        return Locker::with(['activeSubscription.client'])
            ->orderBy('locker_number')
            ->get();
    }

    /**
     * Получить занятые шкафы
     */
    public function getOccupied(): Collection
    {
        return Locker::occupied()
            ->with(['activeSubscription.client'])
            ->orderBy('locker_number')
            ->get();
    }

    /**
     * Создать шкафы
     */
    public function createLockers(int $count, int $startNumber = 1): int
    {
        $created = 0;

        for ($i = $startNumber; $i < $startNumber + $count; $i++) {
            $number = str_pad($i, 3, '0', STR_PAD_LEFT);
            
            if (!Locker::where('locker_number', $number)->exists()) {
                Locker::create([
                    'locker_number' => $number,
                    'status' => LockerStatus::AVAILABLE,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Получить статистику шкафов
     */
    public function getStatistics(): array
    {
        $total = Locker::count();
        $available = Locker::available()->count();
        $occupied = Locker::occupied()->count();

        return [
            'total' => $total,
            'available' => $available,
            'occupied' => $occupied,
            'occupancy_rate' => $total > 0 ? round(($occupied / $total) * 100, 1) : 0,
        ];
    }
}
```

---

## 5. PaymentService

**Файл:** `app/Services/PaymentService.php`

```php
<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Events\PaymentReceived;
use App\Events\PaymentRejected;
use App\Events\PaymentVerified;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    /**
     * Загрузка чека
     */
    public function uploadReceipt(
        BookingRequest $booking,
        string $filePath,
        string $fileName,
        string $fileType
    ): Payment {
        $payment = Payment::updateOrCreate(
            ['booking_request_id' => $booking->id],
            [
                'client_id' => $booking->client_id,
                'amount' => $booking->total_price,
                'receipt_file_path' => $filePath,
                'receipt_file_name' => $fileName,
                'receipt_file_type' => $fileType,
                'status' => PaymentStatus::PENDING,
            ]
        );

        // Обновляем статус бронирования
        $booking->markPaymentSent();

        event(new PaymentReceived($payment));

        return $payment;
    }

    /**
     * Загрузка чека из файла
     */
    public function uploadReceiptFile(BookingRequest $booking, UploadedFile $file): Payment
    {
        $path = $file->store("receipts/{$booking->client_id}", 'public');

        return $this->uploadReceipt(
            $booking,
            $path,
            $file->getClientOriginalName(),
            $file->getMimeType()
        );
    }

    /**
     * Подтверждение платежа
     */
    public function verify(Payment $payment, User $admin): void
    {
        $payment->verify($admin);

        event(new PaymentVerified($payment));
    }

    /**
     * Отклонение платежа
     */
    public function reject(Payment $payment, User $admin, ?string $reason = null): void
    {
        $payment->reject($admin, $reason);

        event(new PaymentRejected($payment, $reason));
    }

    /**
     * Получить ожидающие платежи
     */
    public function getPending()
    {
        return Payment::with(['client', 'bookingRequest'])
            ->pending()
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Удалить файл чека
     */
    public function deleteReceipt(Payment $payment): void
    {
        if ($payment->receipt_file_path) {
            Storage::disk('public')->delete($payment->receipt_file_path);
            
            $payment->update([
                'receipt_file_path' => null,
                'receipt_file_name' => null,
                'receipt_file_type' => null,
            ]);
        }
    }

    /**
     * Получить статистику платежей
     */
    public function getStatistics(): array
    {
        return [
            'pending' => Payment::pending()->count(),
            'verified_today' => Payment::verified()
                ->whereDate('verified_at', today())
                ->count(),
            'total_verified_today' => Payment::verified()
                ->whereDate('verified_at', today())
                ->sum('amount'),
            'rejected_today' => Payment::where('status', PaymentStatus::REJECTED)
                ->whereDate('verified_at', today())
                ->count(),
        ];
    }
}
```

---

## 6. NotificationService

**Файл:** `app/Services/NotificationService.php`

```php
<?php

namespace App\Services;

use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;

class NotificationService
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    /**
     * Уведомление о подтверждении клиента
     */
    public function notifyClientApproved(Client $client): void
    {
        $this->telegramService->notifyClientApproved($client);
    }

    /**
     * Уведомление об отклонении клиента
     */
    public function notifyClientRejected(Client $client): void
    {
        $this->telegramService->notifyClientRejected($client);
    }

    /**
     * Уведомление о необходимости оплаты
     */
    public function notifyPaymentRequired(BookingRequest $booking): void
    {
        $this->telegramService->notifyPaymentRequired(
            $booking->client,
            $booking->total_price
        );
    }

    /**
     * Уведомление о подтверждении оплаты
     */
    public function notifyPaymentVerified(Payment $payment): void
    {
        $this->telegramService->notifyPaymentVerified($payment->client);
    }

    /**
     * Уведомление об отклонении оплаты
     */
    public function notifyPaymentRejected(Payment $payment): void
    {
        $this->telegramService->notifyPaymentRejected(
            $payment->client,
            $payment->rejection_reason
        );
    }

    /**
     * Уведомление о подтверждении бронирования
     */
    public function notifyBookingApproved(BookingRequest $booking, bool $withPayment): void
    {
        $details = $this->buildBookingDetails($booking);
        $this->telegramService->notifyBookingApproved($booking->client, $details);
    }

    /**
     * Уведомление об отклонении бронирования
     */
    public function notifyBookingRejected(BookingRequest $booking): void
    {
        $this->telegramService->notifyBookingRejected(
            $booking->client,
            $booking->admin_notes
        );
    }

    /**
     * Уведомление об истечении подписки
     */
    public function notifySubscriptionExpiring(Subscription $subscription): void
    {
        $this->telegramService->notifySubscriptionExpiring(
            $subscription->client,
            $subscription->subscription_type->label(),
            $subscription->days_remaining
        );
    }

    /**
     * Уведомление администраторам о новом клиенте
     */
    public function notifyAdminsNewClient(Client $client): void
    {
        $adminChatId = config('telegram.admin_chat_id');
        
        if ($adminChatId) {
            $this->telegramService->sendMessage(
                $adminChatId,
                "🆕 *Новая заявка на регистрацию*\n\n" .
                "👤 {$client->display_name}\n" .
                "📱 {$client->phone_number}\n" .
                "🕐 {$client->created_at->format('d.m.Y H:i')}"
            );
        }
    }

    /**
     * Уведомление администраторам о новом бронировании
     */
    public function notifyAdminsNewBooking(BookingRequest $booking): void
    {
        $adminChatId = config('telegram.admin_chat_id');
        
        if ($adminChatId) {
            $this->telegramService->sendMessage(
                $adminChatId,
                "🎯 *Новый запрос на бронирование*\n\n" .
                "👤 {$booking->client->display_name}\n" .
                "📱 {$booking->client->phone_number}\n" .
                "🏷️ {$booking->service_type->label()}\n" .
                "💰 \${$booking->total_price}"
            );
        }
    }

    /**
     * Уведомление администраторам о полученном чеке
     */
    public function notifyAdminsPaymentReceived(Payment $payment): void
    {
        $adminChatId = config('telegram.admin_chat_id');
        
        if ($adminChatId) {
            $this->telegramService->sendMessage(
                $adminChatId,
                "💳 *Получен чек*\n\n" .
                "👤 {$payment->client->display_name}\n" .
                "💰 \${$payment->amount}\n" .
                "🏷️ Заявка #{$payment->booking_request_id}"
            );
        }
    }

    /**
     * Формирование деталей бронирования
     */
    protected function buildBookingDetails(BookingRequest $booking): string
    {
        $details = "Услуга: {$booking->service_type->label()}\n";

        if ($booking->hasGame()) {
            $details .= "Тип игры: {$booking->game_subscription_type->label()}\n";
        }

        if ($booking->hasLocker()) {
            $details .= "Шкаф: на {$booking->locker_duration_months} мес.\n";
            
            // Получаем назначенный шкаф
            $lockerSubscription = $booking->client
                ->activeSubscriptions()
                ->lockerSubscriptions()
                ->latest()
                ->first();
            
            if ($lockerSubscription?->locker) {
                $details .= "Номер шкафа: #{$lockerSubscription->locker->locker_number}\n";
            }
        }

        $details .= "Стоимость: \${$booking->total_price}";

        return $details;
    }
}
```

---

## 7. Events

### 7.1 ClientRegistered

**Файл:** `app/Events/ClientRegistered.php`

```php
<?php

namespace App\Events;

use App\Models\Client;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Client $client
    ) {}
}
```

### 7.2 ClientApproved

**Файл:** `app/Events/ClientApproved.php`

```php
<?php

namespace App\Events;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Client $client,
        public User $admin
    ) {}
}
```

### 7.3 BookingCreated

**Файл:** `app/Events/BookingCreated.php`

```php
<?php

namespace App\Events;

use App\Models\BookingRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public BookingRequest $booking
    ) {}
}
```

### 7.4 Другие события

Создайте аналогичные файлы для:
- `ClientRejected`
- `BookingApproved`
- `BookingRejected`
- `PaymentRequested`
- `PaymentReceived`
- `PaymentVerified`
- `PaymentRejected`
- `SubscriptionActivated`
- `SubscriptionExpiring`
- `SubscriptionExpired`

---

## 8. Listeners

### 8.1 SendClientNotification

**Файл:** `app/Listeners/SendClientNotification.php`

```php
<?php

namespace App\Listeners;

use App\Events\ClientApproved;
use App\Events\ClientRejected;
use App\Services\NotificationService;

class SendClientNotification
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function handleApproved(ClientApproved $event): void
    {
        $this->notificationService->notifyClientApproved($event->client);
    }

    public function handleRejected(ClientRejected $event): void
    {
        $this->notificationService->notifyClientRejected($event->client);
    }
}
```

### 8.2 EventServiceProvider

**Файл:** `app/Providers/EventServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Events\BookingCreated;
use App\Events\ClientApproved;
use App\Events\ClientRegistered;
use App\Events\ClientRejected;
use App\Events\PaymentReceived;
use App\Events\PaymentRequested;
use App\Events\PaymentVerified;
use App\Events\SubscriptionExpiring;
use App\Listeners\SendAdminNotification;
use App\Listeners\SendClientNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ClientRegistered::class => [
            [SendAdminNotification::class, 'handleNewClient'],
        ],
        
        ClientApproved::class => [
            [SendClientNotification::class, 'handleApproved'],
        ],
        
        ClientRejected::class => [
            [SendClientNotification::class, 'handleRejected'],
        ],
        
        BookingCreated::class => [
            [SendAdminNotification::class, 'handleNewBooking'],
        ],
        
        PaymentRequested::class => [
            [SendClientNotification::class, 'handlePaymentRequired'],
        ],
        
        PaymentReceived::class => [
            [SendAdminNotification::class, 'handlePaymentReceived'],
        ],
        
        PaymentVerified::class => [
            [SendClientNotification::class, 'handlePaymentVerified'],
        ],
        
        SubscriptionExpiring::class => [
            [SendClientNotification::class, 'handleSubscriptionExpiring'],
        ],
    ];
}
```

---

## 9. Критерии завершения этапа

- [ ] Все сервисы созданы и работают
- [ ] Регистрация клиента через бота создает запись в БД
- [ ] Подтверждение клиента в админке отправляет уведомление
- [ ] Создание бронирования работает корректно
- [ ] Подтверждение бронирования активирует подписки
- [ ] Запрос оплаты отправляет реквизиты клиенту
- [ ] Загрузка чека сохраняет файл и создает платеж
- [ ] Подтверждение оплаты активирует подписки
- [ ] Шкафы назначаются автоматически
- [ ] Все уведомления отправляются корректно
