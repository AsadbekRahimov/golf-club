# Техническая архитектура системы

## 1. Обзор архитектуры

### 1.1 Высокоуровневая схема

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           GOLF CLUB SYSTEM                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────┐              ┌─────────────────────┐              │
│  │    TELEGRAM BOT     │              │    ADMIN PANEL      │              │
│  │   (Клиентский UI)   │              │  (Laravel Orchid)   │              │
│  └──────────┬──────────┘              └──────────┬──────────┘              │
│             │                                    │                          │
│             │ Webhook                            │ HTTP                     │
│             ▼                                    ▼                          │
│  ┌─────────────────────────────────────────────────────────────┐           │
│  │                    LARAVEL APPLICATION                       │           │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │           │
│  │  │ Controllers │  │  Services   │  │  Events/Listeners   │  │           │
│  │  └──────┬──────┘  └──────┬──────┘  └──────────┬──────────┘  │           │
│  │         │                │                    │              │           │
│  │         └────────────────┼────────────────────┘              │           │
│  │                          ▼                                   │           │
│  │              ┌─────────────────────┐                        │           │
│  │              │   Eloquent Models   │                        │           │
│  │              └──────────┬──────────┘                        │           │
│  └─────────────────────────┼───────────────────────────────────┘           │
│                            │                                               │
│                            ▼                                               │
│  ┌─────────────────────────────────────────────────────────────┐           │
│  │                    MySQL/PostgreSQL                          │           │
│  └─────────────────────────────────────────────────────────────┘           │
│                                                                             │
│  ┌─────────────────────┐  ┌─────────────────────┐                          │
│  │   Laravel Queue     │  │   File Storage      │                          │
│  │   (Redis/Database)  │  │   (local/S3)        │                          │
│  └─────────────────────┘  └─────────────────────┘                          │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Технологический стек

| Компонент | Технология | Версия |
|-----------|------------|--------|
| Backend Framework | Laravel | 11.x |
| PHP | PHP | 8.2+ |
| Admin Panel | Laravel Orchid | 14.x |
| Telegram Bot | irazasyed/telegram-bot-sdk | 3.x | ✅ Установлен |
| Database | MySQL / PostgreSQL | 8.0+ / 15+ |
| Queue | Laravel Queue (Database/Redis) | - |
| Cache | Redis / File | - |
| File Storage | Laravel Storage (local/S3) | - |

---

## 2. Структура директорий

```
golf-club/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Telegram/
│   │   │       ├── WebhookController.php
│   │   │       └── Commands/
│   │   │           ├── StartCommand.php
│   │   │           ├── MenuCommand.php
│   │   │           └── ...
│   │   ├── Middleware/
│   │   │   └── VerifyTelegramWebhook.php
│   │   └── Requests/
│   │       ├── ClientRequest.php
│   │       ├── BookingRequest.php
│   │       └── PaymentRequest.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Client.php
│   │   ├── Locker.php
│   │   ├── Subscription.php
│   │   ├── BookingRequest.php
│   │   ├── Payment.php
│   │   └── Setting.php
│   │
│   ├── Services/
│   │   ├── ClientService.php
│   │   ├── BookingService.php
│   │   ├── PaymentService.php
│   │   ├── LockerService.php
│   │   ├── SubscriptionService.php
│   │   ├── NotificationService.php
│   │   └── TelegramService.php
│   │
│   ├── Orchid/
│   │   ├── Screens/
│   │   │   ├── Dashboard/
│   │   │   │   └── DashboardScreen.php
│   │   │   ├── Client/
│   │   │   │   ├── ClientListScreen.php
│   │   │   │   ├── ClientEditScreen.php
│   │   │   │   └── ClientPendingScreen.php
│   │   │   ├── Booking/
│   │   │   │   ├── BookingListScreen.php
│   │   │   │   └── BookingProcessScreen.php
│   │   │   ├── Payment/
│   │   │   │   ├── PaymentListScreen.php
│   │   │   │   └── PaymentVerifyScreen.php
│   │   │   ├── Locker/
│   │   │   │   ├── LockerListScreen.php
│   │   │   │   └── LockerEditScreen.php
│   │   │   ├── Subscription/
│   │   │   │   ├── SubscriptionListScreen.php
│   │   │   │   └── SubscriptionEditScreen.php
│   │   │   └── Setting/
│   │   │       └── SettingScreen.php
│   │   │
│   │   ├── Layouts/
│   │   │   ├── Client/
│   │   │   ├── Booking/
│   │   │   ├── Payment/
│   │   │   ├── Locker/
│   │   │   └── Subscription/
│   │   │
│   │   ├── Filters/
│   │   │   ├── ClientStatusFilter.php
│   │   │   ├── SubscriptionTypeFilter.php
│   │   │   └── PaymentStatusFilter.php
│   │   │
│   │   └── PlatformProvider.php
│   │
│   ├── Events/
│   │   ├── ClientRegistered.php
│   │   ├── ClientApproved.php
│   │   ├── ClientRejected.php
│   │   ├── BookingCreated.php
│   │   ├── BookingApproved.php
│   │   ├── BookingRejected.php
│   │   ├── PaymentRequested.php
│   │   ├── PaymentReceived.php
│   │   ├── PaymentVerified.php
│   │   ├── PaymentRejected.php
│   │   ├── SubscriptionActivated.php
│   │   └── SubscriptionExpiring.php
│   │
│   ├── Listeners/
│   │   ├── SendClientNotification.php
│   │   ├── SendAdminNotification.php
│   │   ├── ActivateSubscription.php
│   │   ├── AssignLocker.php
│   │   └── ReleaseLocker.php
│   │
│   ├── Jobs/
│   │   ├── SendTelegramMessage.php
│   │   ├── ProcessPayment.php
│   │   └── CheckExpiringSubscriptions.php
│   │
│   ├── Console/
│   │   └── Commands/
│   │       ├── CheckSubscriptions.php
│   │       └── SetTelegramWebhook.php
│   │
│   └── Enums/
│       ├── ClientStatus.php
│       ├── SubscriptionType.php
│       ├── SubscriptionStatus.php
│       ├── BookingStatus.php
│       ├── PaymentStatus.php
│       └── LockerStatus.php
│
├── config/
│   ├── telegram.php
│   └── golf-club.php
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
│
├── routes/
│   ├── web.php
│   ├── platform.php
│   └── telegram.php
│
├── resources/
│   └── views/
│       └── telegram/
│           └── messages/
│
├── storage/
│   └── app/
│       └── receipts/
│
└── tests/
    ├── Feature/
    └── Unit/
```

---

## 3. Компоненты системы

### 3.1 Модели (Models)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           DOMAIN MODELS                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────┐     ┌──────────────┐     ┌──────────────┐                    │
│  │  User    │     │   Client     │     │   Locker     │                    │
│  │ (Admin)  │     │              │     │              │                    │
│  └────┬─────┘     └──────┬───────┘     └──────┬───────┘                    │
│       │                  │                    │                            │
│       │ approves         │ has many           │ assigned to                │
│       ▼                  ▼                    ▼                            │
│  ┌──────────────────────────────────────────────────────────┐              │
│  │                    Subscription                          │              │
│  │  - game_once                                             │              │
│  │  - game_monthly                                          │              │
│  │  - locker                                                │              │
│  └──────────────────────────────────────────────────────────┘              │
│                          │                                                 │
│                          │ created from                                    │
│                          ▼                                                 │
│  ┌──────────────────────────────────────────────────────────┐              │
│  │                   BookingRequest                         │              │
│  │  - pending → payment_required → payment_sent →           │              │
│  │    approved/rejected                                     │              │
│  └──────────────────────────────────────────────────────────┘              │
│                          │                                                 │
│                          │ has one                                         │
│                          ▼                                                 │
│  ┌──────────────────────────────────────────────────────────┐              │
│  │                      Payment                             │              │
│  │  - pending → verified/rejected                           │              │
│  └──────────────────────────────────────────────────────────┘              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Сервисы (Services)

| Сервис | Ответственность |
|--------|-----------------|
| `ClientService` | CRUD клиентов, регистрация, подтверждение |
| `BookingService` | Создание и обработка запросов на бронирование |
| `PaymentService` | Управление платежами и чеками |
| `LockerService` | Управление шкафами, назначение/освобождение |
| `SubscriptionService` | Создание, активация, отмена подписок |
| `NotificationService` | Отправка уведомлений клиентам и админам |
| `TelegramService` | Взаимодействие с Telegram Bot API |

### 3.3 События и слушатели (Events/Listeners)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        EVENT-DRIVEN ARCHITECTURE                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Event                          Listeners                                  │
│  ─────                          ─────────                                  │
│                                                                             │
│  ClientRegistered        ──▶    SendAdminNotification                      │
│                          ──▶    SendWelcomeMessage                         │
│                                                                             │
│  ClientApproved          ──▶    SendClientNotification                     │
│                                                                             │
│  ClientRejected          ──▶    SendClientNotification                     │
│                                                                             │
│  BookingCreated          ──▶    SendAdminNotification                      │
│                                                                             │
│  BookingApproved         ──▶    ActivateSubscription (если без оплаты)     │
│                          ──▶    SendClientNotification                     │
│                                                                             │
│  PaymentRequested        ──▶    SendPaymentDetails                         │
│                                                                             │
│  PaymentReceived         ──▶    SendAdminNotification                      │
│                                                                             │
│  PaymentVerified         ──▶    ActivateSubscription                       │
│                          ──▶    AssignLocker (если есть шкаф)              │
│                          ──▶    SendClientNotification                     │
│                                                                             │
│  SubscriptionActivated   ──▶    SendClientNotification                     │
│                                                                             │
│  SubscriptionExpiring    ──▶    SendExpirationReminder                     │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Telegram Bot архитектура

### 4.1 Обработка webhook

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        TELEGRAM WEBHOOK FLOW                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Telegram Server                                                            │
│       │                                                                     │
│       │ POST /telegram/webhook                                              │
│       ▼                                                                     │
│  ┌──────────────────────┐                                                  │
│  │  WebhookController   │                                                  │
│  └──────────┬───────────┘                                                  │
│             │                                                               │
│             ▼                                                               │
│  ┌──────────────────────┐                                                  │
│  │   Update Parser      │──▶ Определение типа обновления:                  │
│  └──────────┬───────────┘    - Message                                     │
│             │                - Callback Query                               │
│             │                - Photo/Document                               │
│             ▼                                                               │
│  ┌──────────────────────────────────────────────────────────┐              │
│  │                    Router/Dispatcher                      │              │
│  └──────────────────────────────────────────────────────────┘              │
│             │                                                               │
│    ┌────────┼────────┬────────────┬────────────┐                           │
│    ▼        ▼        ▼            ▼            ▼                           │
│ /start  /menu    Callback    Contact      File                             │
│   │       │      Query       Share        Upload                           │
│   ▼       ▼        │           │            │                              │
│ Start  Menu      Booking    Register     Receipt                           │
│ Flow   Flow      Flow       Client       Upload                            │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Состояния бота (Bot States)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         BOT STATE MACHINE                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│                     ┌──────────────┐                                       │
│                     │    START     │                                       │
│                     └──────┬───────┘                                       │
│                            │                                                │
│                            ▼                                                │
│                     ┌──────────────┐                                       │
│              ┌──────│ PHONE_INPUT  │──────┐                                │
│              │      └──────────────┘      │                                │
│              │                            │                                │
│      New Client                    Existing Client                         │
│              │                            │                                │
│              ▼                            │                                │
│       ┌──────────────┐                    │                                │
│       │   PENDING    │                    │                                │
│       │  APPROVAL    │                    │                                │
│       └──────┬───────┘                    │                                │
│              │                            │                                │
│      Approved│                            │                                │
│              │                            │                                │
│              ▼                            ▼                                │
│       ┌──────────────────────────────────────┐                             │
│       │              MAIN_MENU               │                             │
│       └──────────────────┬───────────────────┘                             │
│                          │                                                  │
│         ┌────────────────┼────────────────┐                                │
│         ▼                ▼                ▼                                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                        │
│  │    MY       │  │   BOOKING   │  │   PROFILE   │                        │
│  │SUBSCRIPTIONS│  │   PROCESS   │  │             │                        │
│  └─────────────┘  └──────┬──────┘  └─────────────┘                        │
│                          │                                                  │
│         ┌────────────────┼────────────────┐                                │
│         ▼                ▼                ▼                                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                        │
│  │SELECT_SERVICE│ │SELECT_OPTION│  │  CONFIRM    │                        │
│  └─────────────┘  └─────────────┘  └──────┬──────┘                        │
│                                           │                                │
│                                           ▼                                │
│                                    ┌─────────────┐                         │
│                                    │  WAITING    │                         │
│                                    │  APPROVAL   │                         │
│                                    └──────┬──────┘                         │
│                                           │                                │
│                          ┌────────────────┴────────────────┐               │
│                          ▼                                 ▼               │
│                   ┌─────────────┐                   ┌─────────────┐        │
│                   │  PAYMENT    │                   │  ACTIVATED  │        │
│                   │  REQUIRED   │                   │             │        │
│                   └──────┬──────┘                   └─────────────┘        │
│                          │                                                  │
│                          ▼                                                  │
│                   ┌─────────────┐                                          │
│                   │   UPLOAD    │                                          │
│                   │   RECEIPT   │                                          │
│                   └─────────────┘                                          │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 4.3 Callback структура

```php
// Формат callback_data: action:param1:param2

// Примеры:
"booking:select_service"
"booking:game:once"
"booking:game:monthly"
"booking:locker:3"        // 3 месяца
"booking:both:monthly:6"  // ежемесячная игра + 6 месяцев шкаф
"booking:confirm:123"     // подтвердить запрос ID 123
"menu:subscriptions"
"menu:profile"
```

---

## 5. Admin Panel архитектура (Laravel Orchid)

### 5.1 Структура экранов

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        ORCHID SCREENS STRUCTURE                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Dashboard                                                                  │
│  └── DashboardScreen (статистика, последние запросы)                       │
│                                                                             │
│  Клиенты                                                                   │
│  ├── ClientListScreen (список всех клиентов)                               │
│  ├── ClientEditScreen (редактирование/просмотр клиента)                    │
│  └── ClientPendingScreen (ожидающие подтверждения)                         │
│                                                                             │
│  Бронирования                                                              │
│  ├── BookingListScreen (все запросы на бронирование)                       │
│  └── BookingProcessScreen (обработка конкретного запроса)                  │
│                                                                             │
│  Платежи                                                                   │
│  ├── PaymentListScreen (все платежи)                                       │
│  └── PaymentVerifyScreen (проверка чека)                                   │
│                                                                             │
│  Шкафы                                                                     │
│  ├── LockerListScreen (все шкафы и их статусы)                             │
│  └── LockerEditScreen (управление шкафом)                                  │
│                                                                             │
│  Подписки                                                                  │
│  ├── SubscriptionListScreen (все подписки)                                 │
│  └── SubscriptionEditScreen (управление подпиской)                         │
│                                                                             │
│  Настройки                                                                 │
│  └── SettingScreen (системные настройки)                                   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Меню админ-панели

```php
// PlatformProvider.php

Menu::make('Dashboard')
    ->icon('bs.speedometer2')
    ->route('platform.dashboard')
    ->title('Навигация'),

Menu::make('Клиенты')
    ->icon('bs.people')
    ->list([
        Menu::make('Все клиенты')
            ->route('platform.clients'),
        Menu::make('Ожидают подтверждения')
            ->route('platform.clients.pending')
            ->badge(fn() => Client::pending()->count()),
    ]),

Menu::make('Бронирования')
    ->icon('bs.calendar-check')
    ->route('platform.bookings')
    ->badge(fn() => BookingRequest::pending()->count()),

Menu::make('Платежи')
    ->icon('bs.credit-card')
    ->route('platform.payments')
    ->badge(fn() => Payment::pending()->count()),

Menu::make('Шкафы')
    ->icon('bs.archive')
    ->route('platform.lockers'),

Menu::make('Подписки')
    ->icon('bs.card-checklist')
    ->route('platform.subscriptions'),

Menu::make('Настройки')
    ->icon('bs.gear')
    ->route('platform.settings'),
```

---

## 6. Queue система

### 6.1 Jobs структура

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           QUEUE JOBS                                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Очередь: default                                                          │
│  ├── SendTelegramMessage        (отправка сообщений клиентам)              │
│  ├── SendAdminTelegramMessage   (уведомления админам)                      │
│  └── ProcessPaymentReceipt      (обработка загруженного чека)              │
│                                                                             │
│  Очередь: notifications                                                    │
│  ├── SendExpirationReminder     (напоминания об истечении)                 │
│  └── SendDailyReport            (ежедневный отчет админам)                 │
│                                                                             │
│  Scheduler (cron)                                                          │
│  ├── CheckExpiringSubscriptions (каждый день в 09:00)                      │
│  ├── ExpireSubscriptions        (каждый день в 00:01)                      │
│  └── ReleaseExpiredLockers      (каждый день в 00:05)                      │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 6.2 Конфигурация очередей

```php
// config/queue.php

'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ],
],

// Для production рекомендуется Redis:
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'default',
    'retry_after' => 90,
],
```

---

## 7. Безопасность

### 7.1 Уровни защиты

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SECURITY LAYERS                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Layer 1: Network                                                          │
│  ├── HTTPS обязателен для webhook                                          │
│  ├── IP whitelist для Telegram серверов (опционально)                      │
│  └── Rate limiting                                                         │
│                                                                             │
│  Layer 2: Application                                                      │
│  ├── CSRF protection (Laravel middleware)                                  │
│  ├── XSS protection (Blade escaping)                                       │
│  ├── SQL injection protection (Eloquent ORM)                               │
│  └── Input validation (Form Requests)                                      │
│                                                                             │
│  Layer 3: Authentication                                                   │
│  ├── Admin: Laravel Orchid authentication                                  │
│  ├── Bot: Telegram ID + Phone verification                                 │
│  └── API: Token-based (если нужно)                                         │
│                                                                             │
│  Layer 4: Authorization                                                    │
│  ├── Orchid Permissions для админов                                        │
│  ├── Client status check для бота                                          │
│  └── Ownership verification для данных                                     │
│                                                                             │
│  Layer 5: Data                                                             │
│  ├── Passwords: bcrypt hashing                                             │
│  ├── Sensitive data: encryption at rest                                    │
│  └── File uploads: validation + secure storage                             │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 7.2 Валидация Telegram Webhook

```php
// Middleware: VerifyTelegramWebhook

public function handle($request, Closure $next)
{
    $secretToken = config('telegram.webhook_secret');
    
    if ($request->header('X-Telegram-Bot-Api-Secret-Token') !== $secretToken) {
        abort(401, 'Unauthorized');
    }
    
    return $next($request);
}
```

---

## 8. Конфигурация

### 8.1 Environment переменные

```env
# Telegram Bot
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=random_secret_string

# Admin Telegram IDs (для уведомлений)
TELEGRAM_ADMIN_IDS=123456789,987654321

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=golf_club
DB_USERNAME=root
DB_PASSWORD=secret

# Queue
QUEUE_CONNECTION=database

# File Storage
FILESYSTEM_DISK=local
```

### 8.2 Конфигурационный файл приложения

```php
// config/golf-club.php

return [
    'phone_format' => '/^\+998\s?\d{2}\s?\d{3}[-\s]?\d{2}[-\s]?\d{2}$/',
    
    'locker' => [
        'monthly_price' => 10.00,
        'min_months' => 1,
    ],
    
    'notifications' => [
        'expiration_days_before' => 3,
    ],
    
    'subscription_types' => [
        'game_once' => 'Единоразовая игра',
        'game_monthly' => 'Ежемесячная подписка',
        'locker' => 'Аренда шкафа',
    ],
];
```

---

## 9. Диаграмма развертывания

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        DEPLOYMENT DIAGRAM                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────┐           │
│  │                      Production Server                       │           │
│  │                                                              │           │
│  │   ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │           │
│  │   │    Nginx     │  │    PHP-FPM   │  │   Supervisor │      │           │
│  │   │  (reverse    │──│   (Laravel)  │  │   (queues)   │      │           │
│  │   │   proxy)     │  │              │  │              │      │           │
│  │   └──────────────┘  └──────────────┘  └──────────────┘      │           │
│  │          │                  │                  │             │           │
│  │          │                  │                  │             │           │
│  │   ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │           │
│  │   │    MySQL     │  │    Redis     │  │   Storage    │      │           │
│  │   │  (database)  │  │   (cache/    │  │   (files)    │      │           │
│  │   │              │  │   queues)    │  │              │      │           │
│  │   └──────────────┘  └──────────────┘  └──────────────┘      │           │
│  │                                                              │           │
│  └─────────────────────────────────────────────────────────────┘           │
│                           │                                                 │
│                           │ HTTPS                                          │
│                           │                                                 │
│  ┌─────────────────────────────────────────────────────────────┐           │
│  │                    Telegram Servers                          │           │
│  │                    (webhook calls)                           │           │
│  └─────────────────────────────────────────────────────────────┘           │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 10. Мониторинг и логирование

### 10.1 Логи

```php
// Каналы логирования

'channels' => [
    'telegram' => [
        'driver' => 'daily',
        'path' => storage_path('logs/telegram.log'),
        'level' => 'debug',
        'days' => 14,
    ],
    
    'payments' => [
        'driver' => 'daily',
        'path' => storage_path('logs/payments.log'),
        'level' => 'info',
        'days' => 30,
    ],
    
    'subscriptions' => [
        'driver' => 'daily',
        'path' => storage_path('logs/subscriptions.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

### 10.2 Метрики для мониторинга

- Количество активных подписок
- Количество новых регистраций в день
- Количество необработанных запросов
- Время ответа webhook
- Ошибки отправки сообщений
- Заполненность шкафов
