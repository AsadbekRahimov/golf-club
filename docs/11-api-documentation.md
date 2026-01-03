# API Документация

## Обзор

Данный документ описывает внутреннее API системы Golf Club для взаимодействия между компонентами.

---

## 1. Telegram Webhook API

### 1.1 Endpoint

```
POST /telegram/webhook
```

**Headers:**
```
Content-Type: application/json
X-Telegram-Bot-Api-Secret-Token: {WEBHOOK_SECRET}
```

**Body:** Telegram Update Object

### 1.2 Поддерживаемые типы обновлений

| Тип | Описание |
|-----|----------|
| `message` | Текстовые сообщения, команды |
| `callback_query` | Нажатия на inline кнопки |
| `message.contact` | Отправка контакта (регистрация) |
| `message.photo` | Отправка фото (чеки) |
| `message.document` | Отправка документов (чеки PDF) |

### 1.3 Команды бота

| Команда | Описание | Доступ |
|---------|----------|--------|
| `/start` | Начало работы / регистрация | Все |
| `/menu` | Главное меню | Подтвержденные |
| `/help` | Справка | Все |

### 1.4 Callback Data форматы

```
# Формат: action:param1:param2:...

# Бронирование
booking:service:game          # Выбор услуги "игра"
booking:service:locker        # Выбор услуги "шкаф"
booking:service:both          # Выбор "комплекс"
booking:game_type:once        # Единоразовая игра
booking:game_type:monthly     # Ежемесячная игра
booking:locker_months:3       # Шкаф на 3 месяца
booking:confirm:game:once     # Подтвердить бронирование
booking:cancel                # Отмена

# Меню
menu:subscriptions            # Мои подписки
menu:profile                  # Профиль
menu:contact                  # Контакты
```

---

## 2. Внутренние сервисы API

### 2.1 ClientService

```php
namespace App\Services;

class ClientService
{
    /**
     * Регистрация нового клиента
     * 
     * @param array $data [
     *     'phone_number' => string,
     *     'telegram_id' => int,
     *     'telegram_chat_id' => int,
     *     'first_name' => ?string,
     *     'last_name' => ?string,
     *     'username' => ?string,
     * ]
     * @return Client
     */
    public function register(array $data): Client;

    /**
     * Подтверждение клиента
     * 
     * @param Client $client
     * @param User $admin
     * @return Client
     */
    public function approve(Client $client, User $admin): Client;

    /**
     * Отклонение клиента
     * 
     * @param Client $client
     * @param string|null $reason
     * @return Client
     */
    public function reject(Client $client, ?string $reason = null): Client;

    /**
     * Поиск по номеру телефона
     * 
     * @param string $phone
     * @return Client|null
     */
    public function findByPhone(string $phone): ?Client;

    /**
     * Поиск по Telegram ID
     * 
     * @param int $telegramId
     * @return Client|null
     */
    public function findByTelegramId(int $telegramId): ?Client;

    /**
     * Нормализация номера телефона
     * 
     * @param string $phone
     * @return string Формат: +998 XX XXX-XX-XX
     */
    public function normalizePhone(string $phone): string;

    /**
     * Валидация номера телефона
     * 
     * @param string $phone
     * @return bool
     */
    public function isValidPhone(string $phone): bool;
}
```

### 2.2 BookingService

```php
namespace App\Services;

class BookingService
{
    /**
     * Создание запроса на бронирование
     * 
     * @param Client $client
     * @param ServiceType $serviceType game|locker|both
     * @param GameSubscriptionType|null $gameType once|monthly
     * @param int|null $lockerMonths
     * @return BookingRequest
     * @throws Exception Если нет свободных шкафов
     */
    public function createBooking(
        Client $client,
        ServiceType $serviceType,
        ?GameSubscriptionType $gameType = null,
        ?int $lockerMonths = null
    ): BookingRequest;

    /**
     * Расчет стоимости
     * 
     * @param ServiceType $serviceType
     * @param GameSubscriptionType|null $gameType
     * @param int|null $lockerMonths
     * @return float
     */
    public function calculatePrice(
        ServiceType $serviceType,
        ?GameSubscriptionType $gameType = null,
        ?int $lockerMonths = null
    ): float;

    /**
     * Подтверждение без оплаты
     * Активирует подписки немедленно
     * 
     * @param BookingRequest $booking
     * @param User $admin
     * @return void
     */
    public function approveWithoutPayment(BookingRequest $booking, User $admin): void;

    /**
     * Запрос оплаты
     * Создает запись Payment, отправляет реквизиты клиенту
     * 
     * @param BookingRequest $booking
     * @param User $admin
     * @return void
     */
    public function requirePayment(BookingRequest $booking, User $admin): void;

    /**
     * Подтверждение оплаты
     * Верифицирует платеж и активирует подписки
     * 
     * @param Payment $payment
     * @param User $admin
     * @return void
     */
    public function verifyPayment(Payment $payment, User $admin): void;

    /**
     * Отклонение запроса
     * 
     * @param BookingRequest $booking
     * @param User $admin
     * @param string|null $reason
     * @return void
     */
    public function reject(BookingRequest $booking, User $admin, ?string $reason = null): void;
}
```

### 2.3 SubscriptionService

```php
namespace App\Services;

class SubscriptionService
{
    /**
     * Создание подписки
     * 
     * @param Client $client
     * @param SubscriptionType $type game_once|game_monthly|locker
     * @param float $price
     * @param Carbon|null $endDate
     * @param BookingRequest|null $booking
     * @param Locker|null $locker
     * @return Subscription
     */
    public function create(
        Client $client,
        SubscriptionType $type,
        float $price,
        ?Carbon $endDate = null,
        ?BookingRequest $booking = null,
        ?Locker $locker = null
    ): Subscription;

    /**
     * Отмена подписки
     * 
     * @param Subscription $subscription
     * @param User $admin
     * @param string|null $reason
     * @return void
     */
    public function cancel(Subscription $subscription, User $admin, ?string $reason = null): void;

    /**
     * Продление подписки
     * 
     * @param Subscription $subscription
     * @param int $months
     * @return Subscription
     */
    public function extend(Subscription $subscription, int $months): Subscription;

    /**
     * Проверка истекающих подписок
     * Отправляет уведомления клиентам
     * 
     * @return Collection
     */
    public function checkExpiring(): Collection;

    /**
     * Обработка истекших подписок
     * Меняет статус, освобождает шкафы
     * 
     * @return int Количество обработанных
     */
    public function processExpired(): int;

    /**
     * Получить активные подписки клиента
     * 
     * @param Client $client
     * @return Collection
     */
    public function getClientSubscriptions(Client $client): Collection;
}
```

### 2.4 LockerService

```php
namespace App\Services;

class LockerService
{
    /**
     * Проверка наличия свободных шкафов
     * 
     * @return bool
     */
    public function hasAvailable(): bool;

    /**
     * Количество свободных шкафов
     * 
     * @return int
     */
    public function availableCount(): int;

    /**
     * Получить первый свободный шкаф
     * 
     * @return Locker|null
     */
    public function getFirstAvailable(): ?Locker;

    /**
     * Назначить шкаф клиенту
     * Помечает шкаф как занятый
     * 
     * @param Client $client
     * @return Locker|null
     */
    public function assignToClient(Client $client): ?Locker;

    /**
     * Освободить шкаф
     * 
     * @param Locker $locker
     * @return void
     */
    public function release(Locker $locker): void;

    /**
     * Создать шкафы
     * 
     * @param int $count
     * @param int $startNumber
     * @return int Количество созданных
     */
    public function createLockers(int $count, int $startNumber = 1): int;
}
```

### 2.5 PaymentService

```php
namespace App\Services;

class PaymentService
{
    /**
     * Загрузка чека
     * 
     * @param BookingRequest $booking
     * @param string $filePath
     * @param string $fileName
     * @param string $fileType
     * @return Payment
     */
    public function uploadReceipt(
        BookingRequest $booking,
        string $filePath,
        string $fileName,
        string $fileType
    ): Payment;

    /**
     * Загрузка чека из файла
     * 
     * @param BookingRequest $booking
     * @param UploadedFile $file
     * @return Payment
     */
    public function uploadReceiptFile(BookingRequest $booking, UploadedFile $file): Payment;

    /**
     * Подтверждение платежа
     * 
     * @param Payment $payment
     * @param User $admin
     * @return void
     */
    public function verify(Payment $payment, User $admin): void;

    /**
     * Отклонение платежа
     * 
     * @param Payment $payment
     * @param User $admin
     * @param string|null $reason
     * @return void
     */
    public function reject(Payment $payment, User $admin, ?string $reason = null): void;

    /**
     * Получить ожидающие платежи
     * 
     * @return Collection
     */
    public function getPending(): Collection;
}
```

### 2.6 TelegramService

```php
namespace App\Services;

class TelegramService
{
    /**
     * Отправить сообщение
     * 
     * @param int $chatId
     * @param string $text Поддерживает Markdown
     * @param array|null $keyboard Inline или Reply keyboard
     * @return void
     */
    public function sendMessage(int $chatId, string $text, ?array $keyboard = null): void;

    /**
     * Уведомление о подтверждении регистрации
     * 
     * @param Client $client
     * @return void
     */
    public function notifyClientApproved(Client $client): void;

    /**
     * Уведомление об отклонении регистрации
     * 
     * @param Client $client
     * @return void
     */
    public function notifyClientRejected(Client $client): void;

    /**
     * Уведомление о необходимости оплаты
     * 
     * @param Client $client
     * @param float $amount
     * @return void
     */
    public function notifyPaymentRequired(Client $client, float $amount): void;

    /**
     * Уведомление о подтверждении оплаты
     * 
     * @param Client $client
     * @return void
     */
    public function notifyPaymentVerified(Client $client): void;

    /**
     * Уведомление об отклонении оплаты
     * 
     * @param Client $client
     * @param string|null $reason
     * @return void
     */
    public function notifyPaymentRejected(Client $client, ?string $reason = null): void;

    /**
     * Уведомление об истечении подписки
     * 
     * @param Client $client
     * @param string $subscriptionType
     * @param int $daysLeft
     * @return void
     */
    public function notifySubscriptionExpiring(Client $client, string $subscriptionType, int $daysLeft): void;

    /**
     * Уведомление о подтверждении бронирования
     * 
     * @param Client $client
     * @param string $details
     * @return void
     */
    public function notifyBookingApproved(Client $client, string $details): void;

    /**
     * Уведомление об отклонении бронирования
     * 
     * @param Client $client
     * @param string|null $reason
     * @return void
     */
    public function notifyBookingRejected(Client $client, ?string $reason = null): void;
}
```

---

## 3. События (Events)

### 3.1 Список событий

| Событие | Описание | Данные |
|---------|----------|--------|
| `ClientRegistered` | Новая регистрация | `Client $client` |
| `ClientApproved` | Клиент подтвержден | `Client $client, User $admin` |
| `ClientRejected` | Клиент отклонен | `Client $client, ?string $reason` |
| `BookingCreated` | Создан запрос на бронирование | `BookingRequest $booking` |
| `BookingApproved` | Бронирование подтверждено | `BookingRequest $booking, bool $withPayment` |
| `BookingRejected` | Бронирование отклонено | `BookingRequest $booking, ?string $reason` |
| `PaymentRequested` | Запрошена оплата | `BookingRequest $booking` |
| `PaymentReceived` | Получен чек | `Payment $payment` |
| `PaymentVerified` | Оплата подтверждена | `Payment $payment` |
| `PaymentRejected` | Оплата отклонена | `Payment $payment, ?string $reason` |
| `SubscriptionActivated` | Подписка активирована | `Subscription $subscription` |
| `SubscriptionExpiring` | Подписка истекает | `Subscription $subscription` |
| `SubscriptionExpired` | Подписка истекла | `Subscription $subscription` |

### 3.2 Подписка на события

```php
// EventServiceProvider.php

protected $listen = [
    ClientRegistered::class => [
        SendAdminNotification::class,
    ],
    ClientApproved::class => [
        SendClientApprovedNotification::class,
    ],
    BookingCreated::class => [
        SendAdminNotification::class,
    ],
    PaymentVerified::class => [
        ActivateSubscriptions::class,
        SendClientNotification::class,
    ],
    SubscriptionExpiring::class => [
        SendExpirationReminder::class,
    ],
];
```

---

## 4. Модели данных

### 4.1 Client

```php
{
    "id": 1,
    "phone_number": "+998 90 123-45-67",
    "telegram_id": 123456789,
    "telegram_chat_id": 123456789,
    "first_name": "Иван",
    "last_name": "Иванов",
    "username": "ivanov",
    "full_name": "Иван Петрович Иванов",
    "status": "approved", // pending|approved|blocked
    "approved_by": 1,
    "approved_at": "2024-01-15T10:30:00Z",
    "rejected_at": null,
    "rejection_reason": null,
    "notes": "VIP клиент",
    "created_at": "2024-01-15T10:00:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
}
```

### 4.2 Locker

```php
{
    "id": 1,
    "locker_number": "001",
    "status": "available", // available|occupied
    "description": "Первый ряд, левая сторона",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
}
```

### 4.3 BookingRequest

```php
{
    "id": 1,
    "client_id": 1,
    "service_type": "both", // game|locker|both
    "game_subscription_type": "monthly", // once|monthly|null
    "locker_duration_months": 3,
    "total_price": 230.00,
    "status": "approved", // pending|payment_required|payment_sent|approved|rejected
    "admin_notes": "Постоянный клиент",
    "processed_by": 1,
    "processed_at": "2024-01-15T11:00:00Z",
    "created_at": "2024-01-15T10:45:00Z",
    "updated_at": "2024-01-15T11:00:00Z"
}
```

### 4.4 Payment

```php
{
    "id": 1,
    "booking_request_id": 1,
    "client_id": 1,
    "amount": 230.00,
    "receipt_file_path": "receipts/1/2024-01-15_abc123.jpg",
    "receipt_file_name": "payment_receipt.jpg",
    "receipt_file_type": "image/jpeg",
    "status": "verified", // pending|verified|rejected
    "verified_by": 1,
    "verified_at": "2024-01-15T11:00:00Z",
    "rejection_reason": null,
    "created_at": "2024-01-15T10:50:00Z",
    "updated_at": "2024-01-15T11:00:00Z"
}
```

### 4.5 Subscription

```php
{
    "id": 1,
    "client_id": 1,
    "booking_request_id": 1,
    "subscription_type": "locker", // game_once|game_monthly|locker
    "locker_id": 5,
    "start_date": "2024-01-15",
    "end_date": "2024-04-15",
    "price": 30.00,
    "status": "active", // active|expired|cancelled
    "cancelled_at": null,
    "cancelled_by": null,
    "cancellation_reason": null,
    "created_at": "2024-01-15T11:00:00Z",
    "updated_at": "2024-01-15T11:00:00Z"
}
```

### 4.6 Setting

```php
{
    "id": 1,
    "key": "game_once_price",
    "value": "50.00",
    "type": "decimal", // string|integer|decimal|boolean|json
    "group": "pricing",
    "description": "Стоимость единоразовой игры ($)",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
}
```

---

## 5. Enums

### 5.1 ClientStatus

```php
enum ClientStatus: string {
    case PENDING = 'pending';     // Ожидает подтверждения
    case APPROVED = 'approved';   // Подтвержден
    case BLOCKED = 'blocked';     // Заблокирован
}
```

### 5.2 LockerStatus

```php
enum LockerStatus: string {
    case AVAILABLE = 'available';  // Свободен
    case OCCUPIED = 'occupied';    // Занят
}
```

### 5.3 ServiceType

```php
enum ServiceType: string {
    case GAME = 'game';     // Только игра
    case LOCKER = 'locker'; // Только шкаф
    case BOTH = 'both';     // Комплекс
}
```

### 5.4 GameSubscriptionType

```php
enum GameSubscriptionType: string {
    case ONCE = 'once';       // Единоразовая
    case MONTHLY = 'monthly'; // Ежемесячная
}
```

### 5.5 SubscriptionType

```php
enum SubscriptionType: string {
    case GAME_ONCE = 'game_once';       // Единоразовая игра
    case GAME_MONTHLY = 'game_monthly'; // Ежемесячная игра
    case LOCKER = 'locker';             // Аренда шкафа
}
```

### 5.6 SubscriptionStatus

```php
enum SubscriptionStatus: string {
    case ACTIVE = 'active';       // Активна
    case EXPIRED = 'expired';     // Истекла
    case CANCELLED = 'cancelled'; // Отменена
}
```

### 5.7 BookingStatus

```php
enum BookingStatus: string {
    case PENDING = 'pending';                   // Ожидает рассмотрения
    case PAYMENT_REQUIRED = 'payment_required'; // Требуется оплата
    case PAYMENT_SENT = 'payment_sent';         // Чек отправлен
    case APPROVED = 'approved';                 // Одобрено
    case REJECTED = 'rejected';                 // Отклонено
}
```

### 5.8 PaymentStatus

```php
enum PaymentStatus: string {
    case PENDING = 'pending';     // Ожидает проверки
    case VERIFIED = 'verified';   // Подтверждено
    case REJECTED = 'rejected';   // Отклонено
}
```

---

## 6. Настройки системы (Settings)

### 6.1 Доступные настройки

| Ключ | Тип | Группа | Описание |
|------|-----|--------|----------|
| `payment_card_number` | string | payment | Номер карты для оплаты |
| `payment_card_holder` | string | payment | Имя владельца карты |
| `contact_phone` | string | contact | Контактный телефон |
| `game_once_price` | decimal | pricing | Цена единоразовой игры |
| `game_monthly_price` | decimal | pricing | Цена месячной подписки |
| `locker_monthly_price` | decimal | pricing | Цена аренды шкафа |
| `notification_days_before` | integer | notifications | Дней до уведомления |
| `welcome_message` | text | messages | Приветственное сообщение |

### 6.2 Методы работы с настройками

```php
// Получить значение
$value = Setting::getValue('game_once_price', 0);

// Установить значение
Setting::setValue('game_once_price', 50.00);

// Хелперы
Setting::getPaymentCardNumber();
Setting::getContactPhone();
Setting::getGameOncePrice();
Setting::getGameMonthlyPrice();
Setting::getLockerMonthlyPrice();
Setting::getNotificationDaysBefore();
```

---

## 7. Коды ошибок

### 7.1 HTTP коды

| Код | Описание |
|-----|----------|
| 200 | Успешный запрос |
| 201 | Ресурс создан |
| 400 | Неверный запрос |
| 401 | Не авторизован |
| 403 | Доступ запрещен |
| 404 | Ресурс не найден |
| 422 | Ошибка валидации |
| 500 | Внутренняя ошибка сервера |

### 7.2 Бизнес-ошибки

| Код | Сообщение |
|-----|-----------|
| `NO_LOCKERS` | Нет свободных шкафов |
| `CLIENT_BLOCKED` | Клиент заблокирован |
| `CLIENT_PENDING` | Клиент ожидает подтверждения |
| `INVALID_PHONE` | Неверный формат номера телефона |
| `BOOKING_NOT_FOUND` | Запрос на бронирование не найден |
| `PAYMENT_NOT_FOUND` | Платеж не найден |
| `FILE_TOO_LARGE` | Файл слишком большой |
| `INVALID_FILE_TYPE` | Недопустимый тип файла |

---

## 8. Лимиты и ограничения

### 8.1 Telegram API

| Ограничение | Значение |
|-------------|----------|
| Сообщений в секунду (на чат) | 1 |
| Сообщений в секунду (всего) | 30 |
| Размер сообщения | 4096 символов |
| Inline кнопок в ряду | 8 |
| Рядов кнопок | 100 |

### 8.2 Файлы

| Ограничение | Значение |
|-------------|----------|
| Максимальный размер файла | 10 MB |
| Допустимые типы | JPG, PNG, GIF, PDF |
| Хранение файлов | 90 дней |

### 8.3 Система

| Ограничение | Значение |
|-------------|----------|
| Минимальный срок аренды шкафа | 1 месяц |
| Дней до уведомления об истечении | 3 |
| Попыток отправки уведомления | 3 |

---

## 9. Примеры использования

### 9.1 Регистрация клиента

```php
$clientService = app(ClientService::class);

$client = $clientService->register([
    'phone_number' => '+998 90 123-45-67',
    'telegram_id' => 123456789,
    'telegram_chat_id' => 123456789,
    'first_name' => 'Иван',
]);

// Подтверждение
$clientService->approve($client, $admin);
```

### 9.2 Создание бронирования

```php
$bookingService = app(BookingService::class);

// Бронирование игры
$booking = $bookingService->createBooking(
    $client,
    ServiceType::GAME,
    GameSubscriptionType::MONTHLY
);

// Подтверждение без оплаты
$bookingService->approveWithoutPayment($booking, $admin);

// Или с оплатой
$bookingService->requirePayment($booking, $admin);
```

### 9.3 Обработка платежа

```php
$paymentService = app(PaymentService::class);
$bookingService = app(BookingService::class);

// Загрузка чека
$payment = $paymentService->uploadReceipt(
    $booking,
    'receipts/1/receipt.jpg',
    'receipt.jpg',
    'image/jpeg'
);

// Подтверждение
$bookingService->verifyPayment($payment, $admin);
```

### 9.4 Работа с настройками

```php
// Получить тариф
$price = Setting::getGameOncePrice(); // 50.00

// Обновить тариф
Setting::setValue('game_once_price', 60.00);

// Очистить кэш
Cache::forget('setting.game_once_price');
```
