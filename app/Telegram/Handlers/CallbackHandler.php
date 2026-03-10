<?php

namespace App\Telegram\Handlers;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Helpers\PaymentMode;
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

        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $callback->getId(),
        ]);

        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $params = array_slice($parts, 1);

        match ($action) {
            'booking' => $this->handleBooking($params),
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
            'confirm' => $this->confirmBooking(array_slice($params, 1)),
            'both_confirm' => $this->confirmBothBooking($params[1] ?? ''),
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
        $withPayment = PaymentMode::isWithPayment();
        $oncePrice = Setting::getGameOncePrice();
        $monthlyPrice = Setting::getGameMonthlyPrice();

        $onceLabel = $withPayment ? "🎯 Единоразовая (\${$oncePrice})" : "🎯 Единоразовая";
        $monthlyLabel = $withPayment ? "📅 Ежемесячная (\${$monthlyPrice})" : "📅 Ежемесячная";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => $onceLabel, 'callback_data' => 'booking:game_type:once']],
                [['text' => $monthlyLabel, 'callback_data' => 'booking:game_type:monthly']],
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
            "Услуга: {$gameType->label()} подписка на игру\n";

        if (PaymentMode::isWithPayment()) {
            $text .= "Стоимость: *\${$price}*\n";
        }

        $text .= "\nПодтвердить бронирование?";

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

        $withPayment = PaymentMode::isWithPayment();
        $price = Setting::getLockerMonthlyPrice();

        $keyboard = [
            'inline_keyboard' => [
                [['text' => $withPayment ? "1 месяц (\${$price})" : "1 месяц", 'callback_data' => 'booking:locker_months:1']],
                [['text' => $withPayment ? "3 месяца (\$" . ($price * 3) . ")" : "3 месяца", 'callback_data' => 'booking:locker_months:3']],
                [['text' => $withPayment ? "6 месяцев (\$" . ($price * 6) . ")" : "6 месяцев", 'callback_data' => 'booking:locker_months:6']],
                [['text' => $withPayment ? "12 месяцев (\$" . ($price * 12) . ")" : "12 месяцев", 'callback_data' => 'booking:locker_months:12']],
                [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $text = "🗄️ *Аренда шкафа*\n\n" .
            "Доступно шкафов: {$available}\n";

        if ($withPayment) {
            $text .= "Стоимость: \${$price}/месяц\n";
        }

        $text .= "\nВыберите срок аренды:";

        $this->editMessage($text, $keyboard);
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
            "Срок: {$months} мес.\n";

        if (PaymentMode::isWithPayment()) {
            $text .= "Стоимость: *\${$price}*\n";
        }

        $text .= "\nПодтвердить бронирование?";

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
                [['text' => '🎯 Единоразовая игра + шкаф', 'callback_data' => 'booking:both_confirm:once']],
                [['text' => '📅 Ежемесячная игра + шкаф', 'callback_data' => 'booking:both_confirm:monthly']],
                [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "📦 *Комплексный пакет*\n\n" .
            "Выберите тип подписки на игру:\n" .
            "(Шкаф будет арендован на 1 месяц)",
            $keyboard
        );
    }

    protected function confirmBothBooking(string $gameType): void
    {
        if (!$this->client) {
            $this->sendAuthError();
            return;
        }

        $gamePrice = $gameType === 'once' ? Setting::getGameOncePrice() : Setting::getGameMonthlyPrice();
        $lockerPrice = Setting::getLockerMonthlyPrice();
        $totalPrice = $gamePrice + $lockerPrice;
        $gameTypeEnum = $gameType === 'once' ? GameSubscriptionType::ONCE : GameSubscriptionType::MONTHLY;

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Подтвердить', 'callback_data' => "booking:confirm:both:{$gameType}"]],
                [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $text = "📦 *Подтверждение бронирования*\n\n" .
            "Услуга: Комплексный пакет\n" .
            "Игра: {$gameTypeEnum->label()} подписка\n" .
            "Шкаф: 1 мес.\n";

        if (PaymentMode::isWithPayment()) {
            $text .= "Стоимость: *\${$totalPrice}*\n" .
                "  - Игра: \${$gamePrice}\n" .
                "  - Шкаф: \${$lockerPrice}\n";
        }

        $text .= "\nПодтвердить бронирование?";

        $this->editMessage($text, $keyboard);
    }

    protected function confirmBooking(array $params): void
    {
        if (!$this->client) {
            $this->sendAuthError();
            return;
        }

        $service = $params[0] ?? '';
        $option = $params[1] ?? '';

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
            null
        );

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
            'both' => [
                'service_type' => ServiceType::BOTH,
                'game_type' => $option === 'once' ? GameSubscriptionType::ONCE : GameSubscriptionType::MONTHLY,
                'locker_months' => 1,
                'price' => ($option === 'once' ? Setting::getGameOncePrice() : Setting::getGameMonthlyPrice()) + Setting::getLockerMonthlyPrice(),
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

    protected function notifyAdminsAboutBooking(BookingRequest $booking): void
    {
        $adminChatId = config('telegram.admin_chat_id');
        
        if (!$adminChatId) {
            return;
        }

        $text = "🎯 *Новый запрос на бронирование*\n\n" .
            "👤 {$this->client->display_name}\n" .
            "📱 {$this->client->phone_number}\n" .
            "🏷️ {$booking->service_type->label()}\n";

        if (PaymentMode::isWithPayment()) {
            $text .= "💰 \${$booking->total_price}\n";
        }

        $text .= "🕐 {$booking->created_at->format('d.m.Y H:i')}";

        $this->telegram->sendMessage([
            'chat_id' => $adminChatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    protected function sendAuthError(): void
    {
        \Log::channel('single')->error('Client is null in booking confirmation', [
            'callback_data' => $this->update->getCallbackQuery()->getData(),
            'from_id' => $this->update->getCallbackQuery()->getFrom()->getId(),
        ]);

        $this->telegram->sendMessage([
            'chat_id' => $this->update->getCallbackQuery()->getMessage()->getChat()->getId(),
            'text' => "❌ *Ошибка*\n\nПроизошла ошибка при обработке запроса.\n\nПожалуйста, используйте /start для повторной авторизации.",
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
