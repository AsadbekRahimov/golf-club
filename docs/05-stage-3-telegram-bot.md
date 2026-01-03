# Этап 3: Telegram Bot

## Обзор этапа

**Цель:** Создать Telegram-бота для клиентов с полным функционалом регистрации и бронирования.

**Длительность:** 5-7 дней

**Зависимости:** Этап 1 (База данных)

**Результат:** Работающий Telegram-бот с регистрацией, меню и процессом бронирования.

---

## Чек-лист задач

- [ ] Установить и настроить telegram-bot-sdk
- [ ] Создать бота в BotFather и получить токен
- [ ] Настроить webhook
- [ ] Реализовать команду /start и регистрацию
- [ ] Реализовать главное меню
- [ ] Реализовать просмотр подписок
- [ ] Реализовать процесс бронирования
- [ ] Реализовать загрузку чеков
- [ ] Реализовать профиль клиента
- [ ] Протестировать все сценарии

---

## 1. Установка и настройка

### 1.1 Пакет Telegram Bot SDK

> ✅ **Пакет уже установлен:** `irazasyed/telegram-bot-sdk` установлен в проекте.

### 1.2 Публикация конфигурации

```bash
php artisan vendor:publish --tag="telegram-config"
```

### 1.3 Конфигурация

**Файл:** `config/telegram.php`

```php
<?php

return [
    'bots' => [
        'golfclub' => [
            'token' => env('TELEGRAM_BOT_TOKEN', ''),
            'certificate_path' => env('TELEGRAM_CERTIFICATE_PATH', ''),
            'webhook_url' => env('TELEGRAM_WEBHOOK_URL', ''),
            'commands' => [],
        ],
    ],

    'default' => 'golfclub',

    'async_requests' => env('TELEGRAM_ASYNC_REQUESTS', false),

    'http_client_handler' => null,

    'resolve_command_dependencies' => true,

    'commands' => [],

    'command_groups' => [],

    'shared_commands' => [],
];
```

### 1.4 Environment переменные

**Файл:** `.env`

```env
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=random_secret_string_here
TELEGRAM_ADMIN_CHAT_ID=123456789
```

---

## 2. Структура бота

### 2.1 Архитектура

```
app/
├── Http/
│   └── Controllers/
│       └── Telegram/
│           └── WebhookController.php
│
├── Telegram/
│   ├── Bot.php                    # Основной класс бота
│   ├── Handlers/
│   │   ├── MessageHandler.php     # Обработка сообщений
│   │   ├── CallbackHandler.php    # Обработка callback
│   │   └── FileHandler.php        # Обработка файлов
│   │
│   ├── Commands/
│   │   ├── StartCommand.php       # /start
│   │   └── MenuCommand.php        # /menu
│   │
│   ├── Conversations/
│   │   ├── RegistrationConversation.php
│   │   ├── BookingConversation.php
│   │   └── UploadReceiptConversation.php
│   │
│   ├── Keyboards/
│   │   ├── MainMenuKeyboard.php
│   │   ├── BookingKeyboard.php
│   │   └── ConfirmKeyboard.php
│   │
│   └── Messages/
│       ├── WelcomeMessage.php
│       ├── SubscriptionMessage.php
│       └── BookingMessage.php
│
└── Services/
    └── TelegramService.php
```

### 2.2 Маршруты

**Файл:** `routes/telegram.php`

```php
<?php

use App\Http\Controllers\Telegram\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [WebhookController::class, 'handle'])
    ->middleware('telegram.webhook')
    ->name('telegram.webhook');
```

**Файл:** `routes/web.php` (добавить)

```php
// Подключить telegram маршруты
require __DIR__.'/telegram.php';
```

---

## 3. Основные компоненты

### 3.1 WebhookController

**Файл:** `app/Http/Controllers/Telegram/WebhookController.php`

```php
<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Telegram\Bot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __construct(
        protected Bot $bot
    ) {}

    public function handle(Request $request): Response
    {
        try {
            $this->bot->handleUpdate($request->all());
        } catch (\Exception $e) {
            report($e);
        }

        return response('OK', 200);
    }
}
```

### 3.2 Webhook Middleware

**Файл:** `app/Http/Middleware/VerifyTelegramWebhook.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secretToken = config('telegram.webhook_secret');
        
        if ($secretToken && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secretToken) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}
```

**Регистрация в** `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'telegram.webhook' => \App\Http\Middleware\VerifyTelegramWebhook::class,
    ]);
})
```

### 3.3 Bot класс

**Файл:** `app/Telegram/Bot.php`

```php
<?php

namespace App\Telegram;

use App\Models\Client;
use App\Telegram\Handlers\CallbackHandler;
use App\Telegram\Handlers\FileHandler;
use App\Telegram\Handlers\MessageHandler;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class Bot
{
    protected Api $telegram;
    protected ?Update $update = null;
    protected ?Client $client = null;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bots.golfclub.token'));
    }

    public function handleUpdate(array $data): void
    {
        $this->update = new Update($data);
        
        Log::channel('telegram')->debug('Incoming update', $data);

        // Определяем клиента
        $this->client = $this->identifyClient();

        // Определяем тип обновления и обрабатываем
        if ($this->update->isType('message')) {
            $this->handleMessage();
        } elseif ($this->update->isType('callback_query')) {
            $this->handleCallback();
        }
    }

    protected function identifyClient(): ?Client
    {
        $from = $this->update->getMessage()?->getFrom() 
            ?? $this->update->getCallbackQuery()?->getFrom();

        if (!$from) {
            return null;
        }

        return Client::where('telegram_id', $from->getId())->first();
    }

    protected function handleMessage(): void
    {
        $message = $this->update->getMessage();
        
        // Проверка на контакт (регистрация)
        if ($message->has('contact')) {
            (new MessageHandler($this->telegram, $this->update, $this->client))
                ->handleContact($message->getContact());
            return;
        }

        // Проверка на фото/документ (загрузка чека)
        if ($message->has('photo') || $message->has('document')) {
            (new FileHandler($this->telegram, $this->update, $this->client))
                ->handle();
            return;
        }

        // Проверка на команду
        $text = $message->getText();
        
        if (str_starts_with($text, '/')) {
            (new MessageHandler($this->telegram, $this->update, $this->client))
                ->handleCommand($text);
            return;
        }

        // Обычное сообщение
        (new MessageHandler($this->telegram, $this->update, $this->client))
            ->handleText($text);
    }

    protected function handleCallback(): void
    {
        (new CallbackHandler($this->telegram, $this->update, $this->client))
            ->handle();
    }

    public function getTelegram(): Api
    {
        return $this->telegram;
    }
}
```

---

## 4. Обработчики (Handlers)

### 4.1 MessageHandler

**Файл:** `app/Telegram/Handlers/MessageHandler.php`

```php
<?php

namespace App\Telegram\Handlers;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Setting;
use App\Telegram\Keyboards\MainMenuKeyboard;
use App\Telegram\Keyboards\RequestContactKeyboard;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Contact;
use Telegram\Bot\Objects\Update;

class MessageHandler
{
    public function __construct(
        protected Api $telegram,
        protected Update $update,
        protected ?Client $client
    ) {}

    public function handleCommand(string $command): void
    {
        $command = strtolower(trim($command));

        match ($command) {
            '/start' => $this->handleStart(),
            '/menu' => $this->handleMenu(),
            '/help' => $this->handleHelp(),
            default => $this->handleUnknownCommand(),
        };
    }

    public function handleText(string $text): void
    {
        // Если клиент не зарегистрирован
        if (!$this->client) {
            $this->requestPhoneNumber();
            return;
        }

        // Если клиент ожидает подтверждения
        if ($this->client->isPending()) {
            $this->sendPendingMessage();
            return;
        }

        // Если заблокирован
        if ($this->client->isBlocked()) {
            $this->sendBlockedMessage();
            return;
        }

        // Обработка текста меню
        $this->handleMenuText($text);
    }

    public function handleContact(Contact $contact): void
    {
        $chatId = $this->update->getMessage()->getChat()->getId();
        $from = $this->update->getMessage()->getFrom();

        // Нормализуем номер телефона
        $phoneNumber = $this->normalizePhoneNumber($contact->getPhoneNumber());

        // Проверяем существующего клиента
        $existingClient = Client::where('phone_number', $phoneNumber)->first();

        if ($existingClient) {
            // Обновляем telegram данные
            $existingClient->update([
                'telegram_id' => $from->getId(),
                'telegram_chat_id' => $chatId,
                'username' => $from->getUsername(),
            ]);

            $this->client = $existingClient;

            if ($existingClient->isApproved()) {
                $this->sendWelcomeBack();
            } else {
                $this->sendPendingMessage();
            }
            return;
        }

        // Создаем нового клиента
        $this->client = Client::create([
            'phone_number' => $phoneNumber,
            'telegram_id' => $from->getId(),
            'telegram_chat_id' => $chatId,
            'first_name' => $from->getFirstName(),
            'last_name' => $from->getLastName(),
            'username' => $from->getUsername(),
            'status' => ClientStatus::PENDING,
        ]);

        // Отправляем сообщение о регистрации
        $this->sendRegistrationConfirmation();

        // Уведомляем админов
        $this->notifyAdminsAboutNewClient();
    }

    protected function handleStart(): void
    {
        if (!$this->client) {
            $this->requestPhoneNumber();
            return;
        }

        if ($this->client->isPending()) {
            $this->sendPendingMessage();
            return;
        }

        if ($this->client->isBlocked()) {
            $this->sendBlockedMessage();
            return;
        }

        $this->sendMainMenu();
    }

    protected function handleMenu(): void
    {
        if ($this->client?->isApproved()) {
            $this->sendMainMenu();
        } else {
            $this->handleStart();
        }
    }

    protected function handleHelp(): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => "🏌️ *Помощь*\n\n" .
                "Доступные команды:\n" .
                "/start - Начать работу с ботом\n" .
                "/menu - Главное меню\n" .
                "/help - Эта справка\n\n" .
                "По всем вопросам обращайтесь к администратору.",
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function handleUnknownCommand(): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => "Неизвестная команда. Используйте /help для списка команд.",
        ]);
    }

    protected function handleMenuText(string $text): void
    {
        match ($text) {
            '📋 Мои подписки' => $this->showSubscriptions(),
            '🎯 Забронировать' => $this->startBooking(),
            '👤 Профиль' => $this->showProfile(),
            '📞 Связаться' => $this->showContact(),
            default => $this->sendMainMenu(),
        };
    }

    protected function requestPhoneNumber(): void
    {
        $keyboard = RequestContactKeyboard::make();

        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => "🏌️ *Добро пожаловать в Golf Club!*\n\n" .
                "Для начала работы, пожалуйста, поделитесь своим номером телефона.",
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    protected function sendRegistrationConfirmation(): void
    {
        $contactPhone = Setting::getContactPhone();

        $message = "✅ *Заявка отправлена!*\n\n" .
            "Ваша заявка на регистрацию принята и находится на рассмотрении.\n" .
            "Мы уведомим вас о результате.\n\n";

        if ($contactPhone) {
            $message .= "📞 Для связи с администрацией: {$contactPhone}";
        }

        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function sendPendingMessage(): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => "⏳ *Ожидание подтверждения*\n\n" .
                "Ваша заявка находится на рассмотрении.\n" .
                "Пожалуйста, дождитесь подтверждения от администратора.",
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function sendBlockedMessage(): void
    {
        $contactPhone = Setting::getContactPhone();

        $message = "🚫 *Доступ ограничен*\n\n" .
            "К сожалению, ваш аккаунт заблокирован.\n";

        if ($contactPhone) {
            $message .= "Для выяснения причин свяжитесь с администрацией: {$contactPhone}";
        }

        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function sendWelcomeBack(): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => "👋 *С возвращением!*\n\n" .
                "Рады снова вас видеть, {$this->client->display_name}!",
            'parse_mode' => 'Markdown',
        ]);

        $this->sendMainMenu();
    }

    protected function sendMainMenu(): void
    {
        $keyboard = MainMenuKeyboard::make();

        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => "🏌️ *Главное меню*\n\nВыберите действие:",
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    protected function showSubscriptions(): void
    {
        $subscriptions = $this->client->activeSubscriptions()
            ->with('locker')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->telegram->sendMessage([
                'chat_id' => $this->getChatId(),
                'text' => "📋 *Мои подписки*\n\n" .
                    "У вас нет активных подписок.\n\n" .
                    "Нажмите «🎯 Забронировать» для оформления услуг.",
                'parse_mode' => 'Markdown',
            ]);
            return;
        }

        $text = "📋 *Мои подписки*\n\n";

        foreach ($subscriptions as $subscription) {
            $text .= "{$subscription->subscription_type->icon()} *{$subscription->subscription_type->label()}*\n";
            $text .= "   Начало: {$subscription->start_date->format('d.m.Y')}\n";
            
            if ($subscription->end_date) {
                $text .= "   Окончание: {$subscription->end_date->format('d.m.Y')}\n";
                
                if ($subscription->is_expiring) {
                    $text .= "   ⚠️ _Истекает через {$subscription->days_remaining} дн._\n";
                }
            }
            
            if ($subscription->locker) {
                $text .= "   🗄️ Шкаф: #{$subscription->locker->locker_number}\n";
            }
            
            $text .= "\n";
        }

        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function startBooking(): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🏌️ Подписка на игру', 'callback_data' => 'booking:service:game']],
                [['text' => '🗄️ Аренда шкафа', 'callback_data' => 'booking:service:locker']],
                [['text' => '📦 Комплексный пакет', 'callback_data' => 'booking:service:both']],
                [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => "🎯 *Бронирование услуг*\n\nВыберите тип услуги:",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard),
        ]);
    }

    protected function showProfile(): void
    {
        $subscriptionsCount = $this->client->activeSubscriptions()->count();

        $text = "👤 *Мой профиль*\n\n" .
            "📱 Телефон: {$this->client->phone_number}\n" .
            "👤 Имя: {$this->client->display_name}\n" .
            "📅 Регистрация: {$this->client->created_at->format('d.m.Y')}\n" .
            "📋 Активных подписок: {$subscriptionsCount}";

        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function showContact(): void
    {
        $contactPhone = Setting::getContactPhone();

        $text = "📞 *Связаться с нами*\n\n";

        if ($contactPhone) {
            $text .= "Телефон: {$contactPhone}\n\n";
        }

        $text .= "Для связи с администрацией позвоните по указанному номеру или напишите нам.";

        $this->telegram->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function notifyAdminsAboutNewClient(): void
    {
        $adminChatId = config('telegram.admin_chat_id');
        
        if (!$adminChatId) {
            return;
        }

        $this->telegram->sendMessage([
            'chat_id' => $adminChatId,
            'text' => "🆕 *Новая заявка на регистрацию*\n\n" .
                "👤 {$this->client->display_name}\n" .
                "📱 {$this->client->phone_number}\n" .
                "🕐 {$this->client->created_at->format('d.m.Y H:i')}",
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function normalizePhoneNumber(string $phone): string
    {
        // Удаляем все кроме цифр и +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Форматируем в +998 xx xxx-xx-xx
        if (preg_match('/^\+?998(\d{2})(\d{3})(\d{2})(\d{2})$/', $phone, $matches)) {
            return "+998 {$matches[1]} {$matches[2]}-{$matches[3]}-{$matches[4]}";
        }

        return $phone;
    }

    protected function getChatId(): int
    {
        return $this->update->getMessage()->getChat()->getId();
    }
}
```

### 4.2 CallbackHandler

**Файл:** `app/Telegram/Handlers/CallbackHandler.php`

```php
<?php

namespace App\Telegram\Handlers;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Setting;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class CallbackHandler
{
    public function __construct(
        protected Api $telegram,
        protected Update $update,
        protected ?Client $client
    ) {}

    public function handle(): void
    {
        $callback = $this->update->getCallbackQuery();
        $data = $callback->getData();

        // Подтверждаем callback
        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $callback->getId(),
        ]);

        // Парсим callback data
        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $params = array_slice($parts, 1);

        match ($action) {
            'booking' => $this->handleBooking($params),
            'confirm' => $this->handleConfirm($params),
            default => null,
        };
    }

    protected function handleBooking(array $params): void
    {
        $step = $params[0] ?? '';

        match ($step) {
            'service' => $this->selectService($params[1] ?? ''),
            'game_type' => $this->selectGameType($params[1] ?? ''),
            'locker_months' => $this->selectLockerMonths($params[1] ?? ''),
            'confirm' => $this->confirmBooking($params),
            'cancel' => $this->cancelBooking(),
            default => null,
        };
    }

    protected function selectService(string $service): void
    {
        match ($service) {
            'game' => $this->showGameOptions(),
            'locker' => $this->showLockerOptions(),
            'both' => $this->showBothOptions(),
            default => null,
        };
    }

    protected function showGameOptions(): void
    {
        $oncePrice = Setting::getGameOncePrice();
        $monthlyPrice = Setting::getGameMonthlyPrice();

        $keyboard = [
            'inline_keyboard' => [
                [['text' => "🎯 Единоразовая ($$oncePrice)", 'callback_data' => 'booking:game_type:once']],
                [['text' => "📅 Ежемесячная ($$monthlyPrice)", 'callback_data' => 'booking:game_type:monthly']],
                [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "🏌️ *Подписка на игру*\n\nВыберите тип подписки:",
            $keyboard
        );
    }

    protected function selectGameType(string $type): void
    {
        $gameType = $type === 'once' ? GameSubscriptionType::ONCE : GameSubscriptionType::MONTHLY;
        $price = $type === 'once' ? Setting::getGameOncePrice() : Setting::getGameMonthlyPrice();

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Подтвердить', 'callback_data' => "booking:confirm:game:{$type}"]],
                [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $text = "🏌️ *Подтверждение бронирования*\n\n" .
            "Услуга: {$gameType->label()} подписка на игру\n" .
            "Стоимость: *\${$price}*\n\n" .
            "Подтвердить бронирование?";

        $this->editMessage($text, $keyboard);
    }

    protected function showLockerOptions(): void
    {
        $available = Locker::availableCount();

        if ($available === 0) {
            $this->editMessage(
                "🗄️ *Аренда шкафа*\n\n" .
                "К сожалению, все шкафы заняты.\n" .
                "Пожалуйста, попробуйте позже.",
                [
                    'inline_keyboard' => [
                        [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']],
                    ],
                ]
            );
            return;
        }

        $price = Setting::getLockerMonthlyPrice();

        $keyboard = [
            'inline_keyboard' => [
                [['text' => "1 месяц (\${$price})", 'callback_data' => 'booking:locker_months:1']],
                [['text' => "3 месяца (\$" . ($price * 3) . ")", 'callback_data' => 'booking:locker_months:3']],
                [['text' => "6 месяцев (\$" . ($price * 6) . ")", 'callback_data' => 'booking:locker_months:6']],
                [['text' => "12 месяцев (\$" . ($price * 12) . ")", 'callback_data' => 'booking:locker_months:12']],
                [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "🗄️ *Аренда шкафа*\n\n" .
            "Доступно шкафов: {$available}\n" .
            "Стоимость: \${$price}/месяц\n\n" .
            "Выберите срок аренды:",
            $keyboard
        );
    }

    protected function selectLockerMonths(string $months): void
    {
        $months = (int) $months;
        $price = Setting::getLockerMonthlyPrice() * $months;

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Подтвердить', 'callback_data' => "booking:confirm:locker:{$months}"]],
                [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $text = "🗄️ *Подтверждение бронирования*\n\n" .
            "Услуга: Аренда шкафа\n" .
            "Срок: {$months} мес.\n" .
            "Стоимость: *\${$price}*\n\n" .
            "Подтвердить бронирование?";

        $this->editMessage($text, $keyboard);
    }

    protected function showBothOptions(): void
    {
        $available = Locker::availableCount();

        if ($available === 0) {
            $this->editMessage(
                "📦 *Комплексный пакет*\n\n" .
                "К сожалению, шкафы недоступны.\n" .
                "Вы можете оформить только подписку на игру.",
                [
                    'inline_keyboard' => [
                        [['text' => '🏌️ Только игра', 'callback_data' => 'booking:service:game']],
                        [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']],
                    ],
                ]
            );
            return;
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🎯 Единоразовая игра', 'callback_data' => 'booking:both_game:once']],
                [['text' => '📅 Ежемесячная игра', 'callback_data' => 'booking:both_game:monthly']],
                [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "📦 *Комплексный пакет*\n\n" .
            "Сначала выберите тип подписки на игру:",
            $keyboard
        );
    }

    protected function confirmBooking(array $params): void
    {
        $service = $params[0] ?? '';
        $option = $params[1] ?? '';

        // Создаем запрос на бронирование
        $bookingData = $this->buildBookingData($service, $option);
        
        $booking = BookingRequest::create([
            'client_id' => $this->client->id,
            'service_type' => $bookingData['service_type'],
            'game_subscription_type' => $bookingData['game_type'],
            'locker_duration_months' => $bookingData['locker_months'],
            'total_price' => $bookingData['price'],
            'status' => BookingStatus::PENDING,
        ]);

        $this->editMessage(
            "✅ *Запрос отправлен!*\n\n" .
            "Ваш запрос на бронирование принят.\n" .
            "Номер заявки: #{$booking->id}\n\n" .
            "Ожидайте подтверждения от администратора.",
            [
                'inline_keyboard' => [
                    [['text' => '📋 Мои подписки', 'callback_data' => 'menu:subscriptions']],
                ],
            ]
        );

        // Уведомляем админов
        $this->notifyAdminsAboutBooking($booking);
    }

    protected function buildBookingData(string $service, string $option): array
    {
        return match ($service) {
            'game' => [
                'service_type' => ServiceType::GAME,
                'game_type' => $option === 'once' ? GameSubscriptionType::ONCE : GameSubscriptionType::MONTHLY,
                'locker_months' => null,
                'price' => $option === 'once' ? Setting::getGameOncePrice() : Setting::getGameMonthlyPrice(),
            ],
            'locker' => [
                'service_type' => ServiceType::LOCKER,
                'game_type' => null,
                'locker_months' => (int) $option,
                'price' => Setting::getLockerMonthlyPrice() * (int) $option,
            ],
            default => [
                'service_type' => ServiceType::GAME,
                'game_type' => GameSubscriptionType::ONCE,
                'locker_months' => null,
                'price' => Setting::getGameOncePrice(),
            ],
        };
    }

    protected function cancelBooking(): void
    {
        $this->editMessage(
            "❌ Бронирование отменено.\n\n" .
            "Используйте /menu для возврата в главное меню.",
            null
        );
    }

    protected function handleConfirm(array $params): void
    {
        // Обработка других подтверждений
    }

    protected function notifyAdminsAboutBooking(BookingRequest $booking): void
    {
        $adminChatId = config('telegram.admin_chat_id');
        
        if (!$adminChatId) {
            return;
        }

        $this->telegram->sendMessage([
            'chat_id' => $adminChatId,
            'text' => "🎯 *Новый запрос на бронирование*\n\n" .
                "👤 {$this->client->display_name}\n" .
                "📱 {$this->client->phone_number}\n" .
                "🏷️ {$booking->service_type->label()}\n" .
                "💰 \${$booking->total_price}\n" .
                "🕐 {$booking->created_at->format('d.m.Y H:i')}",
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function editMessage(string $text, ?array $keyboard): void
    {
        $params = [
            'chat_id' => $this->update->getCallbackQuery()->getMessage()->getChat()->getId(),
            'message_id' => $this->update->getCallbackQuery()->getMessage()->getMessageId(),
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        $this->telegram->editMessageText($params);
    }
}
```

### 4.3 FileHandler

**Файл:** `app/Telegram/Handlers/FileHandler.php`

```php
<?php

namespace App\Telegram\Handlers;

use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class FileHandler
{
    public function __construct(
        protected Api $telegram,
        protected Update $update,
        protected ?Client $client
    ) {}

    public function handle(): void
    {
        if (!$this->client?->isApproved()) {
            $this->sendError('Вы не можете загружать файлы.');
            return;
        }

        // Ищем запрос на бронирование, ожидающий оплаты
        $booking = BookingRequest::where('client_id', $this->client->id)
            ->where('status', BookingStatus::PAYMENT_REQUIRED)
            ->latest()
            ->first();

        if (!$booking) {
            $this->sendError('У вас нет запросов, ожидающих оплаты.');
            return;
        }

        // Получаем файл
        $message = $this->update->getMessage();
        $fileId = null;
        $fileName = 'receipt';
        $fileType = 'application/octet-stream';

        if ($message->has('photo')) {
            $photos = $message->getPhoto();
            $photo = end($photos); // Берем максимальное разрешение
            $fileId = $photo['file_id'];
            $fileName = 'receipt.jpg';
            $fileType = 'image/jpeg';
        } elseif ($message->has('document')) {
            $document = $message->getDocument();
            $fileId = $document->getFileId();
            $fileName = $document->getFileName() ?? 'receipt';
            $fileType = $document->getMimeType() ?? 'application/octet-stream';
        }

        if (!$fileId) {
            $this->sendError('Не удалось получить файл.');
            return;
        }

        // Скачиваем файл
        $filePath = $this->downloadFile($fileId, $fileName);

        if (!$filePath) {
            $this->sendError('Ошибка при загрузке файла.');
            return;
        }

        // Создаем или обновляем платеж
        Payment::updateOrCreate(
            ['booking_request_id' => $booking->id],
            [
                'client_id' => $this->client->id,
                'amount' => $booking->total_price,
                'receipt_file_path' => $filePath,
                'receipt_file_name' => $fileName,
                'receipt_file_type' => $fileType,
                'status' => 'pending',
            ]
        );

        // Обновляем статус запроса
        $booking->markPaymentSent();

        $this->sendSuccess($booking);

        // Уведомляем админов
        $this->notifyAdmins($booking);
    }

    protected function downloadFile(string $fileId, string $fileName): ?string
    {
        try {
            $file = $this->telegram->getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();

            $url = "https://api.telegram.org/file/bot" . 
                   config('telegram.bots.golfclub.token') . 
                   "/{$filePath}";

            $contents = file_get_contents($url);
            
            $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'jpg';
            $storagePath = "receipts/{$this->client->id}/" . 
                          time() . '_' . uniqid() . '.' . $extension;

            Storage::put($storagePath, $contents);

            return $storagePath;
        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }

    protected function sendError(string $message): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->update->getMessage()->getChat()->getId(),
            'text' => "❌ {$message}",
        ]);
    }

    protected function sendSuccess(BookingRequest $booking): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $this->update->getMessage()->getChat()->getId(),
            'text' => "✅ *Чек получен!*\n\n" .
                "Ваш чек по заявке #{$booking->id} отправлен на проверку.\n" .
                "Ожидайте подтверждения от администратора.",
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function notifyAdmins(BookingRequest $booking): void
    {
        $adminChatId = config('telegram.admin_chat_id');
        
        if (!$adminChatId) {
            return;
        }

        $this->telegram->sendMessage([
            'chat_id' => $adminChatId,
            'text' => "💳 *Получен чек*\n\n" .
                "👤 {$this->client->display_name}\n" .
                "📱 {$this->client->phone_number}\n" .
                "💰 \${$booking->total_price}\n" .
                "🏷️ Заявка #{$booking->id}",
            'parse_mode' => 'Markdown',
        ]);
    }
}
```

---

## 5. Клавиатуры (Keyboards)

### 5.1 RequestContactKeyboard

**Файл:** `app/Telegram/Keyboards/RequestContactKeyboard.php`

```php
<?php

namespace App\Telegram\Keyboards;

class RequestContactKeyboard
{
    public static function make(): string
    {
        return json_encode([
            'keyboard' => [
                [
                    [
                        'text' => '📱 Поделиться номером телефона',
                        'request_contact' => true,
                    ],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }
}
```

### 5.2 MainMenuKeyboard

**Файл:** `app/Telegram/Keyboards/MainMenuKeyboard.php`

```php
<?php

namespace App\Telegram\Keyboards;

class MainMenuKeyboard
{
    public static function make(): string
    {
        return json_encode([
            'keyboard' => [
                [
                    ['text' => '📋 Мои подписки'],
                    ['text' => '🎯 Забронировать'],
                ],
                [
                    ['text' => '👤 Профиль'],
                    ['text' => '📞 Связаться'],
                ],
            ],
            'resize_keyboard' => true,
        ]);
    }
}
```

---

## 6. Сервис уведомлений для Telegram

**Файл:** `app/Services/TelegramService.php`

```php
<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Setting;
use Telegram\Bot\Api;

class TelegramService
{
    protected Api $telegram;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bots.golfclub.token'));
    }

    public function sendMessage(int $chatId, string $text, ?array $keyboard = null): void
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        $this->telegram->sendMessage($params);
    }

    public function notifyClientApproved(Client $client): void
    {
        $this->sendMessage(
            $client->telegram_chat_id,
            "✅ *Регистрация подтверждена!*\n\n" .
            "Добро пожаловать в Golf Club!\n" .
            "Теперь вам доступны все функции бота.\n\n" .
            "Используйте /menu для перехода в главное меню."
        );
    }

    public function notifyClientRejected(Client $client): void
    {
        $contactPhone = Setting::getContactPhone();

        $text = "❌ *Заявка отклонена*\n\n" .
            "К сожалению, ваша заявка на регистрацию была отклонена.";

        if ($client->rejection_reason) {
            $text .= "\n\nПричина: {$client->rejection_reason}";
        }

        if ($contactPhone) {
            $text .= "\n\nДля уточнения деталей свяжитесь: {$contactPhone}";
        }

        $this->sendMessage($client->telegram_chat_id, $text);
    }

    public function notifyPaymentRequired(Client $client, float $amount): void
    {
        $cardNumber = Setting::getPaymentCardNumber();
        $cardHolder = Setting::getValue('payment_card_holder');

        $text = "💳 *Требуется оплата*\n\n" .
            "Сумма: *\${$amount}*\n\n";

        if ($cardNumber) {
            $text .= "Реквизиты для перевода:\n" .
                "Карта: `{$cardNumber}`\n";
            
            if ($cardHolder) {
                $text .= "Получатель: {$cardHolder}\n";
            }
        }

        $text .= "\nПосле оплаты отправьте фото или скан чека в этот чат.";

        $this->sendMessage($client->telegram_chat_id, $text);
    }

    public function notifyPaymentVerified(Client $client): void
    {
        $this->sendMessage(
            $client->telegram_chat_id,
            "✅ *Оплата подтверждена!*\n\n" .
            "Ваша подписка активирована.\n" .
            "Используйте /menu для просмотра подписок."
        );
    }

    public function notifyPaymentRejected(Client $client, ?string $reason = null): void
    {
        $text = "❌ *Платеж отклонен*\n\n" .
            "К сожалению, ваш платеж не был подтвержден.";

        if ($reason) {
            $text .= "\n\nПричина: {$reason}";
        }

        $text .= "\n\nПожалуйста, отправьте корректный чек.";

        $this->sendMessage($client->telegram_chat_id, $text);
    }

    public function notifySubscriptionExpiring(Client $client, string $subscriptionType, int $daysLeft): void
    {
        $this->sendMessage(
            $client->telegram_chat_id,
            "⚠️ *Подписка истекает*\n\n" .
            "Ваша подписка «{$subscriptionType}» истекает через {$daysLeft} дн.\n\n" .
            "Для продления обратитесь к администратору или оформите новую подписку."
        );
    }

    public function notifyBookingApproved(Client $client, string $details): void
    {
        $this->sendMessage(
            $client->telegram_chat_id,
            "✅ *Бронирование подтверждено!*\n\n{$details}"
        );
    }

    public function notifyBookingRejected(Client $client, ?string $reason = null): void
    {
        $text = "❌ *Запрос отклонен*\n\n" .
            "Ваш запрос на бронирование был отклонен.";

        if ($reason) {
            $text .= "\n\nПричина: {$reason}";
        }

        $this->sendMessage($client->telegram_chat_id, $text);
    }
}
```

---

## 7. Artisan команда для установки webhook

**Файл:** `app/Console/Commands/SetTelegramWebhook.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--remove : Remove the webhook}';
    protected $description = 'Set or remove Telegram webhook';

    public function handle(): int
    {
        $telegram = new Api(config('telegram.bots.golfclub.token'));

        if ($this->option('remove')) {
            $telegram->removeWebhook();
            $this->info('Webhook removed successfully.');
            return 0;
        }

        $webhookUrl = config('telegram.bots.golfclub.webhook_url');
        
        if (!$webhookUrl) {
            $this->error('Webhook URL is not configured.');
            return 1;
        }

        $params = ['url' => $webhookUrl];

        $secret = config('telegram.webhook_secret');
        if ($secret) {
            $params['secret_token'] = $secret;
        }

        $result = $telegram->setWebhook($params);

        if ($result) {
            $this->info('Webhook set successfully: ' . $webhookUrl);
        } else {
            $this->error('Failed to set webhook.');
            return 1;
        }

        return 0;
    }
}
```

---

## 8. Команды для выполнения

```bash
# 1. Пакет irazasyed/telegram-bot-sdk уже установлен в проекте ✅

# 2. Опубликовать конфиг
php artisan vendor:publish --tag="telegram-config"

# 3. Создать бота в BotFather:
#    - Отправить /newbot
#    - Следовать инструкциям
#    - Получить токен

# 4. Добавить токен в .env
# TELEGRAM_BOT_TOKEN=your_token
# TELEGRAM_WEBHOOK_URL=https://yourdomain.com/telegram/webhook

# 5. Создать директории
mkdir -p app/Telegram/Handlers
mkdir -p app/Telegram/Keyboards
mkdir -p app/Http/Controllers/Telegram
mkdir -p app/Http/Middleware

# 6. Создать файлы (скопировать код из документации)

# 7. Зарегистрировать middleware в bootstrap/app.php

# 8. Установить webhook
php artisan telegram:set-webhook

# 9. Проверить webhook
# - Написать боту /start
# - Проверить логи
```

---

## 9. Критерии завершения этапа

- [ ] Бот отвечает на /start
- [ ] Регистрация через номер телефона работает
- [ ] Существующие клиенты автоматически входят
- [ ] Новые клиенты получают сообщение об ожидании
- [ ] Главное меню отображается для подтвержденных клиентов
- [ ] Просмотр подписок работает
- [ ] Процесс бронирования функционирует
- [ ] Загрузка чеков работает
- [ ] Админы получают уведомления о новых запросах
- [ ] Клиенты получают уведомления о статусах
