# Этап 5: Система уведомлений

## Обзор этапа

**Цель:** Настроить систему очередей и автоматических уведомлений.

**Длительность:** 2-3 дня

**Зависимости:** Этап 4 (Бизнес-логика)

**Результат:** Работающая система очередей с автоматическими уведомлениями.

---

## Чек-лист задач

- [ ] Настроить Laravel Queue
- [ ] Создать Jobs для уведомлений
- [ ] Настроить Scheduler для периодических задач
- [ ] Реализовать уведомления об истечении подписок
- [ ] Протестировать очереди

---

## 1. Настройка Queue

### 1.1 Конфигурация

**Файл:** `.env`

```env
QUEUE_CONNECTION=database
```

### 1.2 Миграция для очередей

```bash
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```

### 1.3 Конфигурация очередей

**Файл:** `config/queue.php`

```php
<?php

return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
```

---

## 2. Jobs

### 2.1 SendTelegramMessage

**Файл:** `app/Jobs/SendTelegramMessage.php`

```php
<?php

namespace App\Jobs;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $chatId,
        public string $message,
        public ?array $keyboard = null
    ) {}

    public function handle(): void
    {
        try {
            $telegram = new Api(config('telegram.bots.golfclub.token'));

            $params = [
                'chat_id' => $this->chatId,
                'text' => $this->message,
                'parse_mode' => 'Markdown',
            ];

            if ($this->keyboard) {
                $params['reply_markup'] = json_encode($this->keyboard);
            }

            $telegram->sendMessage($params);

            Log::channel('telegram')->info('Message sent', [
                'chat_id' => $this->chatId,
            ]);
        } catch (\Exception $e) {
            Log::channel('telegram')->error('Failed to send message', [
                'chat_id' => $this->chatId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('telegram')->error('Job failed permanently', [
            'chat_id' => $this->chatId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### 2.2 SendClientNotificationJob

**Файл:** `app/Jobs/SendClientNotificationJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendClientNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public Client $client,
        public string $type,
        public array $data = []
    ) {}

    public function handle(TelegramService $telegramService): void
    {
        match ($this->type) {
            'approved' => $telegramService->notifyClientApproved($this->client),
            'rejected' => $telegramService->notifyClientRejected($this->client),
            'payment_required' => $telegramService->notifyPaymentRequired(
                $this->client,
                $this->data['amount'] ?? 0
            ),
            'payment_verified' => $telegramService->notifyPaymentVerified($this->client),
            'payment_rejected' => $telegramService->notifyPaymentRejected(
                $this->client,
                $this->data['reason'] ?? null
            ),
            'subscription_expiring' => $telegramService->notifySubscriptionExpiring(
                $this->client,
                $this->data['type'] ?? '',
                $this->data['days'] ?? 0
            ),
            'booking_approved' => $telegramService->notifyBookingApproved(
                $this->client,
                $this->data['details'] ?? ''
            ),
            'booking_rejected' => $telegramService->notifyBookingRejected(
                $this->client,
                $this->data['reason'] ?? null
            ),
            default => null,
        };
    }
}
```

### 2.3 CheckExpiringSubscriptionsJob

**Файл:** `app/Jobs/CheckExpiringSubscriptionsJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckExpiringSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        $daysBefore = Setting::getNotificationDaysBefore();

        $expiring = Subscription::with('client')
            ->active()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [
                now()->toDateString(),
                now()->addDays($daysBefore)->toDateString(),
            ])
            ->whereDoesntHave('notifications', function ($query) use ($daysBefore) {
                $query->where('type', 'expiring')
                    ->where('created_at', '>=', now()->subDays($daysBefore));
            })
            ->get();

        foreach ($expiring as $subscription) {
            $notificationService->notifySubscriptionExpiring($subscription);

            // Помечаем что уведомление отправлено
            $subscription->notifications()->create([
                'type' => 'expiring',
            ]);

            Log::channel('subscriptions')->info('Expiring notification sent', [
                'subscription_id' => $subscription->id,
                'client_id' => $subscription->client_id,
                'end_date' => $subscription->end_date,
            ]);
        }

        Log::channel('subscriptions')->info('Expiring check completed', [
            'notified_count' => $expiring->count(),
        ]);
    }
}
```

### 2.4 ProcessExpiredSubscriptionsJob

**Файл:** `app/Jobs/ProcessExpiredSubscriptionsJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessExpiredSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SubscriptionService $subscriptionService): void
    {
        $count = $subscriptionService->processExpired();

        Log::channel('subscriptions')->info('Expired subscriptions processed', [
            'count' => $count,
        ]);
    }
}
```

### 2.5 SendAdminDailyReportJob

**Файл:** `app/Jobs/SendAdminDailyReportJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAdminDailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TelegramService $telegramService): void
    {
        $adminChatId = config('telegram.admin_chat_id');
        
        if (!$adminChatId) {
            return;
        }

        $stats = $this->collectStatistics();

        $message = "📊 *Ежедневный отчет*\n" .
            "_{$stats['date']}_\n\n" .
            
            "*Клиенты:*\n" .
            "• Новых заявок: {$stats['new_clients']}\n" .
            "• Подтверждено: {$stats['approved_clients']}\n\n" .
            
            "*Бронирования:*\n" .
            "• Новых запросов: {$stats['new_bookings']}\n" .
            "• Обработано: {$stats['processed_bookings']}\n" .
            "• Ожидают: {$stats['pending_bookings']}\n\n" .
            
            "*Платежи:*\n" .
            "• Получено чеков: {$stats['received_payments']}\n" .
            "• Подтверждено: {$stats['verified_payments']}\n" .
            "• Сумма: \${$stats['total_amount']}\n\n" .
            
            "*Подписки:*\n" .
            "• Активировано: {$stats['activated_subscriptions']}\n" .
            "• Истекает скоро: {$stats['expiring_subscriptions']}\n" .
            "• Истекло: {$stats['expired_subscriptions']}";

        $telegramService->sendMessage($adminChatId, $message);
    }

    protected function collectStatistics(): array
    {
        $yesterday = now()->subDay();

        return [
            'date' => now()->format('d.m.Y'),
            
            'new_clients' => Client::whereDate('created_at', $yesterday)->count(),
            'approved_clients' => Client::whereDate('approved_at', $yesterday)->count(),
            
            'new_bookings' => BookingRequest::whereDate('created_at', $yesterday)->count(),
            'processed_bookings' => BookingRequest::whereDate('processed_at', $yesterday)->count(),
            'pending_bookings' => BookingRequest::pending()->count(),
            
            'received_payments' => Payment::whereDate('created_at', $yesterday)->count(),
            'verified_payments' => Payment::whereDate('verified_at', $yesterday)
                ->where('status', 'verified')
                ->count(),
            'total_amount' => Payment::whereDate('verified_at', $yesterday)
                ->where('status', 'verified')
                ->sum('amount'),
            
            'activated_subscriptions' => Subscription::whereDate('created_at', $yesterday)->count(),
            'expiring_subscriptions' => Subscription::expiring()->count(),
            'expired_subscriptions' => Subscription::whereDate('end_date', $yesterday)
                ->where('status', 'expired')
                ->count(),
        ];
    }
}
```

---

## 3. Console Commands

### 3.1 CheckSubscriptions

**Файл:** `app/Console/Commands/CheckSubscriptions.php`

```php
<?php

namespace App\Console\Commands;

use App\Jobs\CheckExpiringSubscriptionsJob;
use App\Jobs\ProcessExpiredSubscriptionsJob;
use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';
    protected $description = 'Check expiring and process expired subscriptions';

    public function handle(): int
    {
        $this->info('Checking expiring subscriptions...');
        CheckExpiringSubscriptionsJob::dispatch();

        $this->info('Processing expired subscriptions...');
        ProcessExpiredSubscriptionsJob::dispatch();

        $this->info('Done!');

        return 0;
    }
}
```

### 3.2 SendDailyReport

**Файл:** `app/Console/Commands/SendDailyReport.php`

```php
<?php

namespace App\Console\Commands;

use App\Jobs\SendAdminDailyReportJob;
use Illuminate\Console\Command;

class SendDailyReport extends Command
{
    protected $signature = 'report:daily';
    protected $description = 'Send daily report to admins';

    public function handle(): int
    {
        SendAdminDailyReportJob::dispatch();

        $this->info('Daily report job dispatched!');

        return 0;
    }
}
```

---

## 4. Scheduler

### 4.1 Настройка расписания

**Файл:** `app/Console/Kernel.php` или `routes/console.php`

```php
<?php

use App\Jobs\CheckExpiringSubscriptionsJob;
use App\Jobs\ProcessExpiredSubscriptionsJob;
use App\Jobs\SendAdminDailyReportJob;
use Illuminate\Support\Facades\Schedule;

// Проверка истекающих подписок каждый день в 9:00
Schedule::job(new CheckExpiringSubscriptionsJob())
    ->dailyAt('09:00')
    ->name('check-expiring-subscriptions')
    ->withoutOverlapping();

// Обработка истекших подписок каждый день в 00:05
Schedule::job(new ProcessExpiredSubscriptionsJob())
    ->dailyAt('00:05')
    ->name('process-expired-subscriptions')
    ->withoutOverlapping();

// Ежедневный отчет в 08:00
Schedule::job(new SendAdminDailyReportJob())
    ->dailyAt('08:00')
    ->name('daily-report')
    ->withoutOverlapping();

// Очистка старых jobs
Schedule::command('queue:prune-failed --hours=168')
    ->weekly();
```

### 4.2 Добавление в cron

```bash
# Добавить в crontab
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Логирование

### 5.1 Настройка каналов логов

**Файл:** `config/logging.php`

```php
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'telegram' => [
            'driver' => 'daily',
            'path' => storage_path('logs/telegram.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'subscriptions' => [
            'driver' => 'daily',
            'path' => storage_path('logs/subscriptions.log'),
            'level' => 'info',
            'days' => 30,
        ],

        'payments' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payments.log'),
            'level' => 'info',
            'days' => 30,
        ],

        'queue' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queue.log'),
            'level' => 'info',
            'days' => 14,
        ],
    ],
];
```

---

## 6. Мониторинг очередей

### 6.1 Supervisor конфигурация

**Файл:** `/etc/supervisor/conf.d/golf-club-worker.conf`

```ini
[program:golf-club-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log
stopwaitsecs=3600
```

### 6.2 Команды управления

```bash
# Запустить worker
php artisan queue:work

# Запустить worker в режиме демона
php artisan queue:work --daemon

# Перезапустить workers после деплоя
php artisan queue:restart

# Посмотреть failed jobs
php artisan queue:failed

# Повторить failed job
php artisan queue:retry {id}

# Повторить все failed jobs
php artisan queue:retry all

# Очистить failed jobs
php artisan queue:flush
```

---

## 7. Оптимизация уведомлений

### 7.1 Батчинг уведомлений

**Файл:** `app/Jobs/SendBatchNotificationsJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\Client;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SendBatchNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Collection $clients,
        public string $message
    ) {}

    public function handle(TelegramService $telegramService): void
    {
        foreach ($this->clients as $client) {
            try {
                $telegramService->sendMessage(
                    $client->telegram_chat_id,
                    $this->message
                );
                
                // Задержка между сообщениями чтобы не превысить лимиты Telegram
                usleep(100000); // 100ms
            } catch (\Exception $e) {
                report($e);
            }
        }
    }
}
```

### 7.2 Rate Limiting

**Файл:** `app/Services/TelegramService.php` (обновление)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

class TelegramService
{
    // ... существующий код ...

    public function sendMessage(int $chatId, string $text, ?array $keyboard = null): void
    {
        $key = "telegram-message:{$chatId}";
        
        // Максимум 1 сообщение в секунду на чат
        if (RateLimiter::tooManyAttempts($key, 1)) {
            // Добавляем в очередь с задержкой
            SendTelegramMessage::dispatch($chatId, $text, $keyboard)
                ->delay(now()->addSeconds(RateLimiter::availableIn($key)));
            return;
        }

        RateLimiter::hit($key, 1);

        // Отправляем напрямую или через очередь
        SendTelegramMessage::dispatch($chatId, $text, $keyboard);
    }
}
```

---

## 8. Команды для выполнения

```bash
# 1. Создать таблицы для очередей
php artisan queue:table
php artisan queue:failed-table
php artisan migrate

# 2. Создать Jobs
php artisan make:job SendTelegramMessage
php artisan make:job SendClientNotificationJob
php artisan make:job CheckExpiringSubscriptionsJob
php artisan make:job ProcessExpiredSubscriptionsJob
php artisan make:job SendAdminDailyReportJob

# 3. Создать Commands
php artisan make:command CheckSubscriptions
php artisan make:command SendDailyReport

# 4. Тестировать очередь
php artisan queue:work --once

# 5. Тестировать scheduler
php artisan schedule:list
php artisan schedule:run

# 6. Запустить worker
php artisan queue:work
```

---

## 9. Критерии завершения этапа

- [ ] Queue настроен и работает
- [ ] Jobs создаются и выполняются
- [ ] Уведомления отправляются через очередь
- [ ] Scheduler запускает задачи по расписанию
- [ ] Истекающие подписки уведомляют клиентов
- [ ] Истекшие подписки автоматически обрабатываются
- [ ] Ежедневный отчет отправляется админам
- [ ] Логи записываются корректно
- [ ] Worker стабильно работает
