# Проектирование базы данных

## 1. ER-диаграмма

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                              ENTITY RELATIONSHIP DIAGRAM                                 │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                         │
│  ┌──────────────┐                                            ┌──────────────┐           │
│  │    users     │                                            │   settings   │           │
│  │  (админы)    │                                            │ (настройки)  │           │
│  ├──────────────┤                                            ├──────────────┤           │
│  │ id           │                                            │ id           │           │
│  │ name         │                                            │ key          │           │
│  │ email        │                                            │ value        │           │
│  │ password     │                                            │ description  │           │
│  └──────┬───────┘                                            └──────────────┘           │
│         │                                                                               │
│         │ approves / processes / verifies                                               │
│         ▼                                                                               │
│  ┌──────────────┐         1:N          ┌──────────────────┐                            │
│  │   clients    │◄─────────────────────│  subscriptions   │                            │
│  │  (клиенты)   │                      │    (подписки)    │                            │
│  ├──────────────┤                      ├──────────────────┤                            │
│  │ id           │                      │ id               │                            │
│  │ phone_number │                      │ client_id        │──────┐                     │
│  │ telegram_id  │                      │ subscription_type│      │                     │
│  │ first_name   │                      │ locker_id        │──┐   │                     │
│  │ last_name    │                      │ start_date       │  │   │                     │
│  │ username     │                      │ end_date         │  │   │                     │
│  │ status       │                      │ price            │  │   │                     │
│  │ approved_by  │                      │ status           │  │   │                     │
│  │ approved_at  │                      └──────────────────┘  │   │                     │
│  └──────┬───────┘                                            │   │                     │
│         │                                                    │   │                     │
│         │ 1:N                                                │   │                     │
│         ▼                                                    │   │                     │
│  ┌──────────────────┐       1:1       ┌──────────────┐       │   │                     │
│  │ booking_requests │◄───────────────▶│   payments   │       │   │                     │
│  │   (запросы)      │                 │  (платежи)   │       │   │                     │
│  ├──────────────────┤                 ├──────────────┤       │   │                     │
│  │ id               │                 │ id           │       │   │                     │
│  │ client_id        │                 │ booking_id   │       │   │                     │
│  │ service_type     │                 │ client_id    │       │   │                     │
│  │ game_sub_type    │                 │ amount       │       │   │                     │
│  │ locker_months    │                 │ receipt_path │       │   │                     │
│  │ total_price      │                 │ status       │       │   │                     │
│  │ status           │                 │ verified_by  │       │   │                     │
│  │ processed_by     │                 │ verified_at  │       │   │                     │
│  │ processed_at     │                 └──────────────┘       │   │                     │
│  └──────────────────┘                                        │   │                     │
│                                                              │   │                     │
│                           ┌──────────────┐                   │   │                     │
│                           │   lockers    │◄──────────────────┘   │                     │
│                           │   (шкафы)    │                       │                     │
│                           ├──────────────┤                       │                     │
│                           │ id           │                       │                     │
│                           │ locker_number│     N:1 (через        │                     │
│                           │ status       │     subscriptions)    │                     │
│                           └──────────────┘◄──────────────────────┘                     │
│                                                                                         │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Описание таблиц

### 2.1 Таблица `users` (Администраторы)

Стандартная таблица Laravel для администраторов системы с расширениями Orchid.

| Поле | Тип | Nullable | Описание |
|------|-----|----------|----------|
| `id` | bigint | NO | Primary key, auto-increment |
| `name` | varchar(255) | NO | ФИО администратора |
| `email` | varchar(255) | NO | Email (уникальный) |
| `email_verified_at` | timestamp | YES | Дата подтверждения email |
| `password` | varchar(255) | NO | Хеш пароля |
| `remember_token` | varchar(100) | YES | Токен "запомнить меня" |
| `permissions` | json | YES | Права доступа Orchid |
| `created_at` | timestamp | YES | Дата создания |
| `updated_at` | timestamp | YES | Дата обновления |

**Индексы:**
- `PRIMARY KEY (id)`
- `UNIQUE (email)`

---

### 2.2 Таблица `clients` (Клиенты гольф-клуба)

| Поле | Тип | Nullable | Default | Описание |
|------|-----|----------|---------|----------|
| `id` | bigint | NO | - | Primary key, auto-increment |
| `phone_number` | varchar(20) | NO | - | Номер телефона (+998 xx xxx-xx-xx) |
| `telegram_id` | bigint | NO | - | ID пользователя в Telegram |
| `telegram_chat_id` | bigint | NO | - | ID чата для отправки сообщений |
| `first_name` | varchar(255) | YES | NULL | Имя из Telegram |
| `last_name` | varchar(255) | YES | NULL | Фамилия из Telegram |
| `username` | varchar(255) | YES | NULL | Username в Telegram |
| `full_name` | varchar(255) | YES | NULL | Полное имя (заполняется админом) |
| `status` | enum | NO | 'pending' | Статус: pending/approved/blocked |
| `approved_by` | bigint | YES | NULL | FK → users.id |
| `approved_at` | timestamp | YES | NULL | Дата подтверждения |
| `rejected_at` | timestamp | YES | NULL | Дата отклонения |
| `rejection_reason` | text | YES | NULL | Причина отклонения |
| `notes` | text | YES | NULL | Заметки администратора |
| `created_at` | timestamp | YES | - | Дата регистрации |
| `updated_at` | timestamp | YES | - | Дата обновления |

**Индексы:**
- `PRIMARY KEY (id)`
- `UNIQUE (phone_number)`
- `UNIQUE (telegram_id)`
- `INDEX (status)`
- `INDEX (approved_by)`

**Enum значения для `status`:**
```php
enum ClientStatus: string {
    case PENDING = 'pending';     // Ожидает подтверждения
    case APPROVED = 'approved';   // Подтвержден
    case BLOCKED = 'blocked';     // Заблокирован
}
```

---

### 2.3 Таблица `lockers` (Шкафы)

| Поле | Тип | Nullable | Default | Описание |
|------|-----|----------|---------|----------|
| `id` | bigint | NO | - | Primary key, auto-increment |
| `locker_number` | varchar(10) | NO | - | Номер шкафа (001, 002...) |
| `status` | enum | NO | 'available' | Статус: available/occupied |
| `description` | text | YES | NULL | Описание/расположение |
| `created_at` | timestamp | YES | - | Дата создания |
| `updated_at` | timestamp | YES | - | Дата обновления |

**Индексы:**
- `PRIMARY KEY (id)`
- `UNIQUE (locker_number)`
- `INDEX (status)`

**Enum значения для `status`:**
```php
enum LockerStatus: string {
    case AVAILABLE = 'available';  // Свободен
    case OCCUPIED = 'occupied';    // Занят
}
```

---

### 2.4 Таблица `subscriptions` (Подписки)

| Поле | Тип | Nullable | Default | Описание |
|------|-----|----------|---------|----------|
| `id` | bigint | NO | - | Primary key, auto-increment |
| `client_id` | bigint | NO | - | FK → clients.id |
| `booking_request_id` | bigint | YES | NULL | FK → booking_requests.id |
| `subscription_type` | enum | NO | - | Тип: game_once/game_monthly/locker |
| `locker_id` | bigint | YES | NULL | FK → lockers.id (только для locker) |
| `start_date` | date | NO | - | Дата начала |
| `end_date` | date | YES | NULL | Дата окончания |
| `price` | decimal(10,2) | NO | - | Стоимость |
| `status` | enum | NO | 'active' | Статус: active/expired/cancelled |
| `cancelled_at` | timestamp | YES | NULL | Дата отмены |
| `cancelled_by` | bigint | YES | NULL | FK → users.id |
| `cancellation_reason` | text | YES | NULL | Причина отмены |
| `created_at` | timestamp | YES | - | Дата создания |
| `updated_at` | timestamp | YES | - | Дата обновления |

**Индексы:**
- `PRIMARY KEY (id)`
- `INDEX (client_id)`
- `INDEX (locker_id)`
- `INDEX (subscription_type)`
- `INDEX (status)`
- `INDEX (end_date)`

**Enum значения для `subscription_type`:**
```php
enum SubscriptionType: string {
    case GAME_ONCE = 'game_once';       // Единоразовая игра
    case GAME_MONTHLY = 'game_monthly'; // Ежемесячная подписка
    case LOCKER = 'locker';             // Аренда шкафа
}
```

**Enum значения для `status`:**
```php
enum SubscriptionStatus: string {
    case ACTIVE = 'active';       // Активна
    case EXPIRED = 'expired';     // Истекла
    case CANCELLED = 'cancelled'; // Отменена
}
```

---

### 2.5 Таблица `booking_requests` (Запросы на бронирование)

| Поле | Тип | Nullable | Default | Описание |
|------|-----|----------|---------|----------|
| `id` | bigint | NO | - | Primary key, auto-increment |
| `client_id` | bigint | NO | - | FK → clients.id |
| `service_type` | enum | NO | - | Тип услуги: game/locker/both |
| `game_subscription_type` | enum | YES | NULL | Тип игры: once/monthly |
| `locker_duration_months` | int | YES | NULL | Срок аренды шкафа (месяцы) |
| `total_price` | decimal(10,2) | NO | - | Общая стоимость |
| `status` | enum | NO | 'pending' | Статус запроса |
| `admin_notes` | text | YES | NULL | Заметки админа |
| `processed_by` | bigint | YES | NULL | FK → users.id |
| `processed_at` | timestamp | YES | NULL | Дата обработки |
| `created_at` | timestamp | YES | - | Дата создания |
| `updated_at` | timestamp | YES | - | Дата обновления |

**Индексы:**
- `PRIMARY KEY (id)`
- `INDEX (client_id)`
- `INDEX (status)`
- `INDEX (created_at)`

**Enum значения для `service_type`:**
```php
enum ServiceType: string {
    case GAME = 'game';     // Только игра
    case LOCKER = 'locker'; // Только шкаф
    case BOTH = 'both';     // Игра + шкаф
}
```

**Enum значения для `status`:**
```php
enum BookingStatus: string {
    case PENDING = 'pending';                   // Ожидает рассмотрения
    case PAYMENT_REQUIRED = 'payment_required'; // Требуется оплата
    case PAYMENT_SENT = 'payment_sent';         // Чек отправлен
    case APPROVED = 'approved';                 // Одобрено
    case REJECTED = 'rejected';                 // Отклонено
}
```

---

### 2.6 Таблица `payments` (Платежи)

| Поле | Тип | Nullable | Default | Описание |
|------|-----|----------|---------|----------|
| `id` | bigint | NO | - | Primary key, auto-increment |
| `booking_request_id` | bigint | NO | - | FK → booking_requests.id |
| `client_id` | bigint | NO | - | FK → clients.id |
| `amount` | decimal(10,2) | NO | - | Сумма платежа |
| `receipt_file_path` | varchar(500) | YES | NULL | Путь к файлу чека |
| `receipt_file_name` | varchar(255) | YES | NULL | Оригинальное имя файла |
| `receipt_file_type` | varchar(50) | YES | NULL | MIME-тип файла |
| `status` | enum | NO | 'pending' | Статус: pending/verified/rejected |
| `verified_by` | bigint | YES | NULL | FK → users.id |
| `verified_at` | timestamp | YES | NULL | Дата проверки |
| `rejection_reason` | text | YES | NULL | Причина отклонения |
| `created_at` | timestamp | YES | - | Дата создания |
| `updated_at` | timestamp | YES | - | Дата обновления |

**Индексы:**
- `PRIMARY KEY (id)`
- `UNIQUE (booking_request_id)`
- `INDEX (client_id)`
- `INDEX (status)`

**Enum значения для `status`:**
```php
enum PaymentStatus: string {
    case PENDING = 'pending';     // Ожидает проверки
    case VERIFIED = 'verified';   // Подтверждено
    case REJECTED = 'rejected';   // Отклонено
}
```

---

### 2.7 Таблица `settings` (Настройки системы)

| Поле | Тип | Nullable | Default | Описание |
|------|-----|----------|---------|----------|
| `id` | bigint | NO | - | Primary key, auto-increment |
| `key` | varchar(100) | NO | - | Ключ настройки |
| `value` | text | YES | NULL | Значение |
| `type` | varchar(50) | NO | 'string' | Тип: string/integer/decimal/boolean/json |
| `group` | varchar(100) | YES | 'general' | Группа настроек |
| `description` | text | YES | NULL | Описание |
| `created_at` | timestamp | YES | - | Дата создания |
| `updated_at` | timestamp | YES | - | Дата обновления |

**Индексы:**
- `PRIMARY KEY (id)`
- `UNIQUE (key)`
- `INDEX (group)`

**Предустановленные настройки:**

| Key | Value | Type | Description |
|-----|-------|------|-------------|
| `payment_card_number` | NULL | string | Номер карты для оплаты |
| `payment_card_holder` | NULL | string | Имя владельца карты |
| `contact_phone` | NULL | string | Контактный телефон |
| `game_once_price` | 0 | decimal | Цена единоразовой игры |
| `game_monthly_price` | 0 | decimal | Цена месячной подписки |
| `locker_monthly_price` | 10.00 | decimal | Цена аренды шкафа |
| `total_lockers_count` | 50 | integer | Общее кол-во шкафов |
| `notification_days_before` | 3 | integer | Дней до уведомления |
| `welcome_message` | NULL | text | Приветственное сообщение |

---

## 3. Связи между таблицами

### 3.1 Диаграмма связей

```
users (1) ──────────────┬──▶ (N) clients.approved_by
                        ├──▶ (N) booking_requests.processed_by
                        ├──▶ (N) payments.verified_by
                        └──▶ (N) subscriptions.cancelled_by

clients (1) ────────────┬──▶ (N) subscriptions
                        ├──▶ (N) booking_requests
                        └──▶ (N) payments

lockers (1) ────────────────▶ (N) subscriptions

booking_requests (1) ───────▶ (1) payments
```

### 3.2 Eloquent отношения

```php
// User.php
public function approvedClients(): HasMany
public function processedBookings(): HasMany
public function verifiedPayments(): HasMany
public function cancelledSubscriptions(): HasMany

// Client.php
public function approvedBy(): BelongsTo  // → User
public function subscriptions(): HasMany
public function activeSubscriptions(): HasMany
public function bookingRequests(): HasMany
public function payments(): HasMany

// Locker.php
public function subscriptions(): HasMany
public function activeSubscription(): HasOne
public function currentClient(): HasOneThrough  // через subscription

// Subscription.php
public function client(): BelongsTo
public function locker(): BelongsTo
public function bookingRequest(): BelongsTo
public function cancelledBy(): BelongsTo  // → User

// BookingRequest.php
public function client(): BelongsTo
public function processedBy(): BelongsTo  // → User
public function payment(): HasOne

// Payment.php
public function bookingRequest(): BelongsTo
public function client(): BelongsTo
public function verifiedBy(): BelongsTo  // → User
```

---

## 4. SQL миграции

### 4.1 Порядок создания миграций

```
1. create_clients_table
2. create_lockers_table
3. create_booking_requests_table
4. create_payments_table
5. create_subscriptions_table
6. create_settings_table
7. add_orchid_fields_to_users_table (если не существует)
```

### 4.2 Примеры SQL

**Создание таблицы clients:**
```sql
CREATE TABLE clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    telegram_id BIGINT NOT NULL,
    telegram_chat_id BIGINT NOT NULL,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    username VARCHAR(255) NULL,
    full_name VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'blocked') NOT NULL DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE INDEX clients_phone_number_unique (phone_number),
    UNIQUE INDEX clients_telegram_id_unique (telegram_id),
    INDEX clients_status_index (status),
    
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Индексы и оптимизация

### 5.1 Рекомендуемые индексы

| Таблица | Индекс | Тип | Назначение |
|---------|--------|-----|------------|
| `clients` | `status` | INDEX | Фильтрация по статусу |
| `subscriptions` | `client_id, status` | COMPOSITE | Активные подписки клиента |
| `subscriptions` | `end_date, status` | COMPOSITE | Истекающие подписки |
| `subscriptions` | `locker_id` | INDEX | Поиск подписки по шкафу |
| `booking_requests` | `status, created_at` | COMPOSITE | Очередь запросов |
| `payments` | `status` | INDEX | Ожидающие проверки |
| `lockers` | `status` | INDEX | Свободные шкафы |

### 5.2 Частые запросы

```sql
-- Клиенты, ожидающие подтверждения
SELECT * FROM clients WHERE status = 'pending' ORDER BY created_at ASC;

-- Активные подписки клиента
SELECT * FROM subscriptions 
WHERE client_id = ? AND status = 'active';

-- Свободные шкафы
SELECT * FROM lockers WHERE status = 'available' ORDER BY locker_number;

-- Истекающие подписки (за N дней)
SELECT s.*, c.telegram_chat_id, c.first_name 
FROM subscriptions s
JOIN clients c ON s.client_id = c.id
WHERE s.status = 'active' 
  AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY);

-- Необработанные запросы на бронирование
SELECT br.*, c.phone_number, c.first_name, c.last_name
FROM booking_requests br
JOIN clients c ON br.client_id = c.id
WHERE br.status = 'pending'
ORDER BY br.created_at ASC;

-- Чеки на проверку
SELECT p.*, br.total_price, br.service_type, c.phone_number
FROM payments p
JOIN booking_requests br ON p.booking_request_id = br.id
JOIN clients c ON p.client_id = c.id
WHERE p.status = 'pending'
ORDER BY p.created_at ASC;
```

---

## 6. Seeders (начальные данные)

### 6.1 Настройки системы

```php
// SettingsSeeder.php

$settings = [
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
    [
        'key' => 'contact_phone',
        'value' => null,
        'type' => 'string',
        'group' => 'contact',
        'description' => 'Контактный телефон для связи',
    ],
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
    [
        'key' => 'notification_days_before',
        'value' => '3',
        'type' => 'integer',
        'group' => 'notifications',
        'description' => 'За сколько дней уведомлять об истечении подписки',
    ],
];
```

### 6.2 Шкафы

```php
// LockersSeeder.php

// Создание 50 шкафов с номерами 001-050
for ($i = 1; $i <= 50; $i++) {
    Locker::create([
        'locker_number' => str_pad($i, 3, '0', STR_PAD_LEFT),
        'status' => LockerStatus::AVAILABLE,
    ]);
}
```

### 6.3 Тестовый администратор

```php
// AdminSeeder.php

User::create([
    'name' => 'Admin',
    'email' => 'admin@golfclub.local',
    'password' => Hash::make('password'),
    'permissions' => [
        'platform.index' => true,
        'platform.systems.roles' => true,
        'platform.systems.users' => true,
    ],
]);
```

---

## 7. Резервное копирование

### 7.1 Стратегия бэкапов

```
┌─────────────────────────────────────────────────────────────┐
│                    BACKUP STRATEGY                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Daily Backup (каждый день в 03:00):                       │
│  • Полный дамп базы данных                                 │
│  • Файлы чеков (storage/app/receipts)                      │
│  • Хранение: 7 дней                                        │
│                                                             │
│  Weekly Backup (каждое воскресенье в 04:00):               │
│  • Полный дамп базы данных                                 │
│  • Все файлы storage                                       │
│  • Хранение: 4 недели                                      │
│                                                             │
│  Monthly Backup (1-е число месяца в 05:00):                │
│  • Полный дамп базы данных                                 │
│  • Все файлы проекта                                       │
│  • Хранение: 12 месяцев                                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 7.2 Команда для бэкапа БД

```bash
# MySQL dump
mysqldump -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} \
  --single-transaction \
  --routines \
  --triggers \
  > backup_$(date +%Y%m%d_%H%M%S).sql

# С сжатием
mysqldump -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} \
  | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

---

## 8. Миграция данных

### 8.1 Версионирование схемы

Все изменения схемы БД должны выполняться через Laravel миграции:

```bash
# Создание новой миграции
php artisan make:migration add_column_to_table

# Применение миграций
php artisan migrate

# Откат последней миграции
php artisan migrate:rollback

# Просмотр статуса миграций
php artisan migrate:status
```

### 8.2 Правила изменения схемы

1. **Никогда не изменять** существующие миграции после их применения на production
2. **Создавать новую миграцию** для любых изменений
3. **Всегда добавлять метод `down()`** для возможности отката
4. **Тестировать миграции** на staging перед production
5. **Делать бэкап** перед применением миграций на production
