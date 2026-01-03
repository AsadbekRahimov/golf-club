# Этап 7: Тестирование

## Обзор этапа

**Цель:** Написать тесты для всех критических компонентов системы.

**Длительность:** 3-4 дня

**Зависимости:** Все предыдущие этапы

**Результат:** Набор тестов с покрытием > 70%.

---

## Чек-лист задач

- [ ] Настроить тестовое окружение
- [ ] Написать Unit тесты для моделей
- [ ] Написать Unit тесты для сервисов
- [ ] Написать Feature тесты для API
- [ ] Написать Feature тесты для админки
- [ ] Написать тесты для Telegram бота
- [ ] Настроить CI/CD для автозапуска тестов

---

## 1. Настройка тестового окружения

### 1.1 Конфигурация PHPUnit

**Файл:** `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
        <exclude>
            <directory>app/Orchid</directory>
        </exclude>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

### 1.2 Базовый TestCase

**Файл:** `tests/TestCase.php`

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Запуск seeders для тестов
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }
}
```

---

## 2. Factories

### 2.1 ClientFactory

**Файл:** `database/factories/ClientFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'phone_number' => '+998 ' . $this->faker->numerify('## ###-##-##'),
            'telegram_id' => $this->faker->unique()->randomNumber(9),
            'telegram_chat_id' => $this->faker->randomNumber(9),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'username' => $this->faker->userName(),
            'status' => ClientStatus::PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ClientStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ClientStatus::BLOCKED,
            'rejected_at' => now(),
        ]);
    }
}
```

### 2.2 LockerFactory

**Файл:** `database/factories/LockerFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\LockerStatus;
use App\Models\Locker;
use Illuminate\Database\Eloquent\Factories\Factory;

class LockerFactory extends Factory
{
    protected $model = Locker::class;
    protected static int $number = 1;

    public function definition(): array
    {
        return [
            'locker_number' => str_pad(self::$number++, 3, '0', STR_PAD_LEFT),
            'status' => LockerStatus::AVAILABLE,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => LockerStatus::OCCUPIED,
        ]);
    }
}
```

### 2.3 BookingRequestFactory

**Файл:** `database/factories/BookingRequestFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Models\BookingRequest;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingRequestFactory extends Factory
{
    protected $model = BookingRequest::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory()->approved(),
            'service_type' => ServiceType::GAME,
            'game_subscription_type' => GameSubscriptionType::ONCE,
            'locker_duration_months' => null,
            'total_price' => 50.00,
            'status' => BookingStatus::PENDING,
        ];
    }

    public function forLocker(int $months = 1): static
    {
        return $this->state(fn(array $attributes) => [
            'service_type' => ServiceType::LOCKER,
            'game_subscription_type' => null,
            'locker_duration_months' => $months,
            'total_price' => 10.00 * $months,
        ]);
    }

    public function forBoth(): static
    {
        return $this->state(fn(array $attributes) => [
            'service_type' => ServiceType::BOTH,
            'game_subscription_type' => GameSubscriptionType::MONTHLY,
            'locker_duration_months' => 1,
            'total_price' => 210.00,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => BookingStatus::APPROVED,
            'processed_at' => now(),
        ]);
    }

    public function paymentRequired(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => BookingStatus::PAYMENT_REQUIRED,
            'processed_at' => now(),
        ]);
    }
}
```

### 2.4 SubscriptionFactory

**Файл:** `database/factories/SubscriptionFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Client;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory()->approved(),
            'subscription_type' => SubscriptionType::GAME_ONCE,
            'start_date' => now(),
            'end_date' => null,
            'price' => 50.00,
            'status' => SubscriptionStatus::ACTIVE,
        ];
    }

    public function gameMonthly(): static
    {
        return $this->state(fn(array $attributes) => [
            'subscription_type' => SubscriptionType::GAME_MONTHLY,
            'end_date' => now()->addMonth(),
            'price' => 200.00,
        ]);
    }

    public function locker(): static
    {
        return $this->state(fn(array $attributes) => [
            'subscription_type' => SubscriptionType::LOCKER,
            'end_date' => now()->addMonth(),
            'price' => 10.00,
        ]);
    }

    public function expiring(int $days = 2): static
    {
        return $this->state(fn(array $attributes) => [
            'end_date' => now()->addDays($days),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => SubscriptionStatus::EXPIRED,
            'end_date' => now()->subDay(),
        ]);
    }
}
```

### 2.5 PaymentFactory

**Файл:** `database/factories/PaymentFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $booking = BookingRequest::factory()->paymentRequired()->create();
        
        return [
            'booking_request_id' => $booking->id,
            'client_id' => $booking->client_id,
            'amount' => $booking->total_price,
            'status' => PaymentStatus::PENDING,
        ];
    }

    public function withReceipt(): static
    {
        return $this->state(fn(array $attributes) => [
            'receipt_file_path' => 'receipts/test/receipt.jpg',
            'receipt_file_name' => 'receipt.jpg',
            'receipt_file_type' => 'image/jpeg',
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => PaymentStatus::VERIFIED,
            'verified_at' => now(),
        ]);
    }
}
```

---

## 3. Unit тесты для моделей

### 3.1 ClientTest

**Файл:** `tests/Unit/Models/ClientTest.php`

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use Tests\TestCase;

class ClientTest extends TestCase
{
    public function test_client_can_be_created(): void
    {
        $client = Client::factory()->create();

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
        ]);
    }

    public function test_client_display_name_returns_full_name_if_set(): void
    {
        $client = Client::factory()->create([
            'full_name' => 'Иван Иванов',
            'first_name' => 'Ivan',
        ]);

        $this->assertEquals('Иван Иванов', $client->display_name);
    }

    public function test_client_display_name_returns_telegram_name_if_no_full_name(): void
    {
        $client = Client::factory()->create([
            'full_name' => null,
            'first_name' => 'Ivan',
            'last_name' => 'Ivanov',
        ]);

        $this->assertEquals('Ivan Ivanov', $client->display_name);
    }

    public function test_client_can_be_approved(): void
    {
        $client = Client::factory()->create();
        $admin = User::factory()->create();

        $client->approve($admin);

        $this->assertEquals(ClientStatus::APPROVED, $client->status);
        $this->assertEquals($admin->id, $client->approved_by);
        $this->assertNotNull($client->approved_at);
    }

    public function test_client_can_be_rejected(): void
    {
        $client = Client::factory()->create();

        $client->reject('Причина отказа');

        $this->assertEquals(ClientStatus::BLOCKED, $client->status);
        $this->assertEquals('Причина отказа', $client->rejection_reason);
        $this->assertNotNull($client->rejected_at);
    }

    public function test_client_status_checks(): void
    {
        $pending = Client::factory()->create(['status' => ClientStatus::PENDING]);
        $approved = Client::factory()->approved()->create();
        $blocked = Client::factory()->blocked()->create();

        $this->assertTrue($pending->isPending());
        $this->assertTrue($approved->isApproved());
        $this->assertTrue($blocked->isBlocked());
    }

    public function test_pending_scope(): void
    {
        Client::factory()->count(3)->create(['status' => ClientStatus::PENDING]);
        Client::factory()->count(2)->approved()->create();

        $this->assertCount(3, Client::pending()->get());
    }
}
```

### 3.2 LockerTest

**Файл:** `tests/Unit/Models/LockerTest.php`

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\LockerStatus;
use App\Models\Locker;
use Tests\TestCase;

class LockerTest extends TestCase
{
    public function test_locker_can_be_created(): void
    {
        $locker = Locker::factory()->create();

        $this->assertDatabaseHas('lockers', [
            'id' => $locker->id,
        ]);
    }

    public function test_locker_is_available_by_default(): void
    {
        $locker = Locker::factory()->create();

        $this->assertTrue($locker->isAvailable());
        $this->assertEquals(LockerStatus::AVAILABLE, $locker->status);
    }

    public function test_locker_can_be_occupied(): void
    {
        $locker = Locker::factory()->create();

        $locker->occupy();

        $this->assertFalse($locker->isAvailable());
        $this->assertEquals(LockerStatus::OCCUPIED, $locker->status);
    }

    public function test_locker_can_be_released(): void
    {
        $locker = Locker::factory()->occupied()->create();

        $locker->release();

        $this->assertTrue($locker->isAvailable());
    }

    public function test_get_first_available_returns_locker(): void
    {
        Locker::factory()->occupied()->create(['locker_number' => '001']);
        $available = Locker::factory()->create(['locker_number' => '002']);

        $result = Locker::getFirstAvailable();

        $this->assertEquals($available->id, $result->id);
    }

    public function test_get_first_available_returns_null_when_none(): void
    {
        Locker::factory()->occupied()->create();

        $result = Locker::getFirstAvailable();

        $this->assertNull($result);
    }

    public function test_available_count(): void
    {
        Locker::factory()->count(5)->create();
        Locker::factory()->count(3)->occupied()->create();

        $this->assertEquals(5, Locker::availableCount());
    }
}
```

### 3.3 SubscriptionTest

**Файл:** `tests/Unit/Models/SubscriptionTest.php`

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Locker;
use App\Models\Subscription;
use App\Models\User;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_subscription_can_be_created(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
        ]);
    }

    public function test_subscription_is_active_by_default(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertTrue($subscription->isActive());
    }

    public function test_subscription_days_remaining(): void
    {
        $subscription = Subscription::factory()->create([
            'end_date' => now()->addDays(5),
        ]);

        $this->assertEquals(5, $subscription->days_remaining);
    }

    public function test_subscription_is_expiring(): void
    {
        $expiring = Subscription::factory()->expiring(2)->create();
        $notExpiring = Subscription::factory()->create([
            'end_date' => now()->addDays(10),
        ]);

        $this->assertTrue($expiring->is_expiring);
        $this->assertFalse($notExpiring->is_expiring);
    }

    public function test_subscription_can_expire(): void
    {
        $locker = Locker::factory()->occupied()->create();
        $subscription = Subscription::factory()->locker()->create([
            'locker_id' => $locker->id,
        ]);

        $subscription->expire();

        $this->assertEquals(SubscriptionStatus::EXPIRED, $subscription->status);
        $this->assertTrue($locker->fresh()->isAvailable());
    }

    public function test_subscription_can_be_cancelled(): void
    {
        $admin = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $subscription->cancel($admin, 'Причина отмены');

        $this->assertEquals(SubscriptionStatus::CANCELLED, $subscription->status);
        $this->assertEquals($admin->id, $subscription->cancelled_by);
        $this->assertEquals('Причина отмены', $subscription->cancellation_reason);
    }

    public function test_expiring_scope(): void
    {
        Subscription::factory()->expiring(2)->create();
        Subscription::factory()->expiring(3)->create();
        Subscription::factory()->create(['end_date' => now()->addDays(10)]);

        $this->assertCount(2, Subscription::expiring(3)->get());
    }
}
```

---

## 4. Unit тесты для сервисов

### 4.1 ClientServiceTest

**Файл:** `tests/Unit/Services/ClientServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientService;
use App\Services\NotificationService;
use Mockery;
use Tests\TestCase;

class ClientServiceTest extends TestCase
{
    protected ClientService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('notifyClientApproved')->andReturnNull();
        $notificationService->shouldReceive('notifyClientRejected')->andReturnNull();

        $this->service = new ClientService($notificationService);
    }

    public function test_register_creates_pending_client(): void
    {
        $data = [
            'phone_number' => '+998 90 123-45-67',
            'telegram_id' => 123456789,
            'telegram_chat_id' => 123456789,
            'first_name' => 'Test',
        ];

        $client = $this->service->register($data);

        $this->assertEquals(ClientStatus::PENDING, $client->status);
        $this->assertDatabaseHas('clients', [
            'phone_number' => '+998 90 123-45-67',
        ]);
    }

    public function test_approve_sets_approved_status(): void
    {
        $client = Client::factory()->create();
        $admin = User::factory()->create();

        $result = $this->service->approve($client, $admin);

        $this->assertEquals(ClientStatus::APPROVED, $result->status);
        $this->assertEquals($admin->id, $result->approved_by);
    }

    public function test_reject_sets_blocked_status(): void
    {
        $client = Client::factory()->create();

        $result = $this->service->reject($client, 'Причина');

        $this->assertEquals(ClientStatus::BLOCKED, $result->status);
        $this->assertEquals('Причина', $result->rejection_reason);
    }

    public function test_normalize_phone(): void
    {
        $this->assertEquals('+998 90 123-45-67', $this->service->normalizePhone('998901234567'));
        $this->assertEquals('+998 90 123-45-67', $this->service->normalizePhone('+998901234567'));
        $this->assertEquals('+998 90 123-45-67', $this->service->normalizePhone('+998 90 123-45-67'));
    }

    public function test_is_valid_phone(): void
    {
        $this->assertTrue($this->service->isValidPhone('+998 90 123-45-67'));
        $this->assertTrue($this->service->isValidPhone('998901234567'));
        $this->assertFalse($this->service->isValidPhone('12345'));
        $this->assertFalse($this->service->isValidPhone('+7 900 123-45-67'));
    }

    public function test_find_by_phone(): void
    {
        $client = Client::factory()->create([
            'phone_number' => '+998 90 123-45-67',
        ]);

        $found = $this->service->findByPhone('998901234567');

        $this->assertEquals($client->id, $found->id);
    }

    public function test_find_by_telegram_id(): void
    {
        $client = Client::factory()->create([
            'telegram_id' => 123456789,
        ]);

        $found = $this->service->findByTelegramId(123456789);

        $this->assertEquals($client->id, $found->id);
    }
}
```

### 4.2 BookingServiceTest

**Файл:** `tests/Unit/Services/BookingServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Enums\SubscriptionStatus;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingService;
use App\Services\LockerService;
use App\Services\NotificationService;
use App\Services\SubscriptionService;
use Mockery;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    protected BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $subscriptionService = app(SubscriptionService::class);
        $lockerService = app(LockerService::class);
        $notificationService = Mockery::mock(NotificationService::class)->shouldIgnoreMissing();

        $this->service = new BookingService(
            $subscriptionService,
            $lockerService,
            $notificationService
        );
    }

    public function test_create_booking_for_game(): void
    {
        $client = Client::factory()->approved()->create();

        $booking = $this->service->createBooking(
            $client,
            ServiceType::GAME,
            GameSubscriptionType::ONCE
        );

        $this->assertEquals(BookingStatus::PENDING, $booking->status);
        $this->assertEquals(ServiceType::GAME, $booking->service_type);
        $this->assertEquals(50.00, $booking->total_price);
    }

    public function test_create_booking_for_locker(): void
    {
        $client = Client::factory()->approved()->create();
        Locker::factory()->create();

        $booking = $this->service->createBooking(
            $client,
            ServiceType::LOCKER,
            null,
            3
        );

        $this->assertEquals(ServiceType::LOCKER, $booking->service_type);
        $this->assertEquals(30.00, $booking->total_price);
    }

    public function test_create_booking_fails_without_available_lockers(): void
    {
        $client = Client::factory()->approved()->create();
        // Не создаем шкафы

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Нет свободных шкафов');

        $this->service->createBooking(
            $client,
            ServiceType::LOCKER,
            null,
            1
        );
    }

    public function test_approve_without_payment_activates_subscription(): void
    {
        $client = Client::factory()->approved()->create();
        $booking = BookingRequest::factory()->create([
            'client_id' => $client->id,
        ]);
        $admin = User::factory()->create();

        $this->service->approveWithoutPayment($booking, $admin);

        $this->assertEquals(BookingStatus::APPROVED, $booking->fresh()->status);
        $this->assertCount(1, $client->activeSubscriptions);
    }

    public function test_require_payment_creates_payment_record(): void
    {
        $booking = BookingRequest::factory()->create();
        $admin = User::factory()->create();

        $this->service->requirePayment($booking, $admin);

        $this->assertEquals(BookingStatus::PAYMENT_REQUIRED, $booking->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'booking_request_id' => $booking->id,
        ]);
    }

    public function test_verify_payment_activates_subscription(): void
    {
        $client = Client::factory()->approved()->create();
        $booking = BookingRequest::factory()->paymentRequired()->create([
            'client_id' => $client->id,
        ]);
        $payment = Payment::factory()->create([
            'booking_request_id' => $booking->id,
            'client_id' => $client->id,
        ]);
        $admin = User::factory()->create();

        $this->service->verifyPayment($payment, $admin);

        $this->assertEquals(BookingStatus::APPROVED, $booking->fresh()->status);
        $this->assertCount(1, $client->activeSubscriptions);
    }
}
```

### 4.3 LockerServiceTest

**Файл:** `tests/Unit/Services/LockerServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Enums\LockerStatus;
use App\Models\Client;
use App\Models\Locker;
use App\Services\LockerService;
use Tests\TestCase;

class LockerServiceTest extends TestCase
{
    protected LockerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LockerService();
    }

    public function test_has_available_returns_true_when_lockers_exist(): void
    {
        Locker::factory()->create();

        $this->assertTrue($this->service->hasAvailable());
    }

    public function test_has_available_returns_false_when_no_lockers(): void
    {
        $this->assertFalse($this->service->hasAvailable());
    }

    public function test_assign_to_client_returns_locker(): void
    {
        Locker::factory()->create();
        $client = Client::factory()->approved()->create();

        $locker = $this->service->assignToClient($client);

        $this->assertNotNull($locker);
        $this->assertEquals(LockerStatus::OCCUPIED, $locker->status);
    }

    public function test_assign_to_client_returns_null_when_none_available(): void
    {
        $client = Client::factory()->approved()->create();

        $locker = $this->service->assignToClient($client);

        $this->assertNull($locker);
    }

    public function test_release_makes_locker_available(): void
    {
        $locker = Locker::factory()->occupied()->create();

        $this->service->release($locker);

        $this->assertEquals(LockerStatus::AVAILABLE, $locker->fresh()->status);
    }

    public function test_create_lockers(): void
    {
        $created = $this->service->createLockers(5, 1);

        $this->assertEquals(5, $created);
        $this->assertEquals(5, Locker::count());
    }

    public function test_statistics(): void
    {
        Locker::factory()->count(7)->create();
        Locker::factory()->count(3)->occupied()->create();

        $stats = $this->service->getStatistics();

        $this->assertEquals(10, $stats['total']);
        $this->assertEquals(7, $stats['available']);
        $this->assertEquals(3, $stats['occupied']);
        $this->assertEquals(30.0, $stats['occupancy_rate']);
    }
}
```

---

## 5. Feature тесты

### 5.1 ClientApiTest

**Файл:** `tests/Feature/ClientApiTest.php`

```php
<?php

namespace Tests\Feature;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    public function test_admin_can_view_clients_list(): void
    {
        $admin = User::factory()->create();
        Client::factory()->count(5)->create();

        $response = $this->actingAs($admin)
            ->get(route('platform.clients'));

        $response->assertOk();
    }

    public function test_admin_can_view_pending_clients(): void
    {
        $admin = User::factory()->create();
        Client::factory()->count(3)->create(['status' => ClientStatus::PENDING]);

        $response = $this->actingAs($admin)
            ->get(route('platform.clients.pending'));

        $response->assertOk();
    }

    public function test_admin_can_approve_client(): void
    {
        $admin = User::factory()->create();
        $client = Client::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('platform.clients.edit', $client), [
                'method' => 'approve',
            ]);

        $this->assertEquals(ClientStatus::APPROVED, $client->fresh()->status);
    }
}
```

### 5.2 BookingApiTest

**Файл:** `tests/Feature/BookingApiTest.php`

```php
<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Locker;
use App\Models\User;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    public function test_admin_can_view_bookings_list(): void
    {
        $admin = User::factory()->create();
        BookingRequest::factory()->count(5)->create();

        $response = $this->actingAs($admin)
            ->get(route('platform.bookings'));

        $response->assertOk();
    }

    public function test_admin_can_process_booking(): void
    {
        $admin = User::factory()->create();
        $booking = BookingRequest::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('platform.bookings.process', $booking));

        $response->assertOk();
    }

    public function test_booking_with_locker_assigns_locker(): void
    {
        $admin = User::factory()->create();
        Locker::factory()->create();
        $booking = BookingRequest::factory()->forLocker()->create();

        $this->actingAs($admin)
            ->post(route('platform.bookings.process', $booking), [
                'method' => 'approveWithoutPayment',
            ]);

        $this->assertEquals(BookingStatus::APPROVED, $booking->fresh()->status);
        $this->assertCount(1, Locker::occupied()->get());
    }
}
```

---

## 6. Запуск тестов

### 6.1 Команды

```bash
# Запустить все тесты
php artisan test

# Запустить с покрытием
php artisan test --coverage

# Запустить конкретный тест
php artisan test --filter=ClientTest

# Запустить группу тестов
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Параллельный запуск
php artisan test --parallel
```

### 6.2 Пример вывода

```
PASS  Tests\Unit\Models\ClientTest
✓ client can be created
✓ client display name returns full name if set
✓ client can be approved
✓ client status checks
✓ pending scope

PASS  Tests\Unit\Services\ClientServiceTest
✓ register creates pending client
✓ approve sets approved status
✓ normalize phone
✓ is valid phone

Tests:  42 passed
Time:   2.34s
```

---

## 7. Критерии завершения этапа

- [ ] Все factories созданы
- [ ] Unit тесты для моделей написаны
- [ ] Unit тесты для сервисов написаны
- [ ] Feature тесты для основных сценариев написаны
- [ ] Покрытие кода > 70%
- [ ] Все тесты проходят успешно
- [ ] CI настроен для автозапуска тестов
