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
        $token = config('telegram.bots.golfclub.token');
        if ($token) {
            $this->telegram = new Api($token);
        }
    }

    public function sendMessage(int $chatId, string $text, ?array $keyboard = null): bool
    {
        if (!isset($this->telegram)) {
            return false;
        }

        try {
            $params = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ];

            if ($keyboard) {
                $params['reply_markup'] = json_encode($keyboard);
            }

            $this->telegram->sendMessage($params);
            return true;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    public function notifyClientApproved(Client $client): bool
    {
        $keyboard = \App\Telegram\Keyboards\MainMenuKeyboard::make();
        
        return $this->sendMessage(
            $client->telegram_chat_id,
            "✅ *Регистрация подтверждена!*\n\n" .
            "Добро пожаловать в Golf Club!\n" .
            "Теперь вам доступны все функции бота.\n\n" .
            "Выберите действие в меню:",
            $keyboard
        );
    }

    public function notifyClientRejected(Client $client): bool
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

        return $this->sendMessage($client->telegram_chat_id, $text);
    }

    public function notifySubscriptionExpiring(Client $client, string $subscriptionType, int $daysLeft): bool
    {
        return $this->sendMessage(
            $client->telegram_chat_id,
            "⚠️ *Подписка истекает*\n\n" .
            "Ваша подписка «{$subscriptionType}» истекает через {$daysLeft} дн.\n\n" .
            "Для продления обратитесь к администратору или оформите новую подписку."
        );
    }

    public function notifyBookingApproved(Client $client, string $details): bool
    {
        return $this->sendMessage(
            $client->telegram_chat_id,
            "✅ *Бронирование подтверждено!*\n\n{$details}"
        );
    }

    public function notifyBookingRejected(Client $client, ?string $reason = null): bool
    {
        $text = "❌ *Запрос отклонен*\n\n" .
            "Ваш запрос на бронирование был отклонен.";

        if ($reason) {
            $text .= "\n\nПричина: {$reason}";
        }

        return $this->sendMessage($client->telegram_chat_id, $text);
    }

    public function notifyAdmins(string $message): bool
    {
        $adminChatId = config('telegram.admin_chat_id');

        if (!$adminChatId) {
            return false;
        }

        return $this->sendMessage((int) $adminChatId, $message);
    }
}
