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
        $command = strtolower(trim(explode(' ', $command)[0]));

        match ($command) {
            '/start' => $this->handleStart(),
            '/menu' => $this->handleMenu(),
            '/help' => $this->handleHelp(),
            default => $this->handleUnknownCommand(),
        };
    }

    public function handleText(string $text): void
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

        $this->handleMenuText($text);
    }

    public function handleContact(Contact $contact): void
    {
        $chatId = $this->update->getMessage()->getChat()->getId();
        $from = $this->update->getMessage()->getFrom();

        $phoneNumber = $this->normalizePhoneNumber($contact->getPhoneNumber());

        $existingClient = Client::where('phone_number', $phoneNumber)->first();

        if ($existingClient) {
            $existingClient->update([
                'telegram_id' => (string) $from->getId(),
                'telegram_chat_id' => (string) $chatId,
                'username' => $from->getUsername(),
                'first_name' => $existingClient->first_name ?: ($contact->getFirstName() ?: $from->getFirstName()),
                'last_name' => $existingClient->last_name ?: ($contact->getLastName() ?: $from->getLastName()),
            ]);

            $this->client = $existingClient;

            if ($existingClient->isApproved()) {
                $this->sendWelcomeBack();
            } else {
                $this->sendPendingMessage();
            }
            return;
        }

        $this->client = Client::create([
            'phone_number' => $phoneNumber,
            'telegram_id' => (string) $from->getId(),
            'telegram_chat_id' => (string) $chatId,
            'first_name' => $contact->getFirstName() ?: $from->getFirstName(),
            'last_name' => $contact->getLastName() ?: $from->getLastName(),
            'username' => $from->getUsername(),
            'status' => ClientStatus::PENDING,
        ]);

        $this->sendRegistrationConfirmation();
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

        $text .= "Для связи с администрацией позвоните по указанному номеру.";

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
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
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
