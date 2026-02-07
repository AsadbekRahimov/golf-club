<?php

namespace App\Telegram\Handlers;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Setting;
use Carbon\Carbon;
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
        $rest = array_slice($params, 1);

        match ($step) {
            'service' => $this->selectService($rest[0] ?? ''),
            'game_type' => $this->selectGameType($rest[0] ?? ''),
            'locker_months' => $this->selectLockerMonths($rest[0] ?? ''),
            'both_game' => $this->selectBothGameType($rest[0] ?? ''),
            'both_months' => $this->selectBothLockerMonths($rest[0] ?? '', $rest[1] ?? '1'),
            'time' => $this->showTimeSlots($rest),
            'confirm' => $this->confirmBooking($rest),
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

    // ===== Game Flow =====

    protected function showGameOptions(): void
    {
        $oncePrice = Setting::getGameOncePrice();
        $monthlyPrice = Setting::getGameMonthlyPrice();

        $keyboard = [
            'inline_keyboard' => [
                [['text' => "🎯 Единоразовая (\${$oncePrice})", 'callback_data' => 'booking:game_type:once']],
                [['text' => "📅 Ежемесячная (\${$monthlyPrice})", 'callback_data' => 'booking:game_type:monthly']],
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
        $this->showDateOptions('g', $type);
    }

    // ===== Locker Flow =====

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

    // ===== Both (Complex Package) Flow =====

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
                [['text' => '🎯 Единоразовая игра + шкаф', 'callback_data' => 'booking:both_game:once']],
                [['text' => '📅 Ежемесячная игра + шкаф', 'callback_data' => 'booking:both_game:monthly']],
                [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "📦 *Комплексный пакет*\n\n" .
            "Выберите тип подписки на игру:",
            $keyboard
        );
    }

    protected function selectBothGameType(string $gameType): void
    {
        $gt = $gameType === 'once' ? 'o' : 'm';
        $price = Setting::getLockerMonthlyPrice();

        $gameLabel = $gameType === 'once' ? 'Единоразовая' : 'Ежемесячная';

        $keyboard = [
            'inline_keyboard' => [
                [['text' => "1 месяц (\${$price})", 'callback_data' => "booking:both_months:{$gt}:1"]],
                [['text' => "3 месяца (\$" . ($price * 3) . ")", 'callback_data' => "booking:both_months:{$gt}:3"]],
                [['text' => "6 месяцев (\$" . ($price * 6) . ")", 'callback_data' => "booking:both_months:{$gt}:6"]],
                [['text' => "12 месяцев (\$" . ($price * 12) . ")", 'callback_data' => "booking:both_months:{$gt}:12"]],
                [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "📦 *Комплексный пакет*\n\n" .
            "Игра: {$gameLabel}\n\n" .
            "Выберите срок аренды шкафа:",
            $keyboard
        );
    }

    protected function selectBothLockerMonths(string $gameTypeShort, string $months): void
    {
        $this->showDateOptions('b', $gameTypeShort, $months);
    }

    // ===== Date & Time Selection =====

    protected function showDateOptions(string $serviceCtx, string ...$ctxParams): void
    {
        $ctxStr = implode(':', array_merge([$serviceCtx], $ctxParams));
        $buttons = [];

        for ($i = 0; $i < 7; $i++) {
            $date = now()->addDays($i);
            $label = match ($i) {
                0 => 'Сегодня, ' . $date->format('d.m'),
                1 => 'Завтра, ' . $date->format('d.m'),
                default => $this->formatDayName($date) . ', ' . $date->format('d.m'),
            };
            $buttons[] = [['text' => "📅 {$label}", 'callback_data' => "booking:time:{$ctxStr}:{$i}"]];
        }

        $buttons[] = [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']];

        $this->editMessage(
            "📅 *Выберите дату посещения:*",
            ['inline_keyboard' => $buttons]
        );
    }

    protected function showTimeSlots(array $params): void
    {
        $dayOffset = (int) end($params);
        $date = now()->addDays($dayOffset);
        $ctxStr = implode(':', $params);

        $startHour = 8;
        if ($dayOffset === 0) {
            $currentHour = (int) now()->format('H');
            $startHour = max(8, $currentHour + 1);
            if ($startHour % 2 !== 0) {
                $startHour++;
            }
        }

        $buttons = [];
        $row = [];
        for ($hour = $startHour; $hour <= 20; $hour += 2) {
            $row[] = [
                'text' => sprintf('%02d:00', $hour),
                'callback_data' => "booking:confirm:{$ctxStr}:{$hour}",
            ];
            if (count($row) === 3) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $buttons[] = $row;
        }

        if (empty($buttons)) {
            $this->editMessage(
                "🕐 На сегодня нет доступных слотов.\nВыберите другой день.",
                ['inline_keyboard' => [[['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']]]]
            );
            return;
        }

        $buttons[] = [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']];

        $dateLabel = $this->formatDayName($date) . ', ' . $date->format('d.m');

        $this->editMessage(
            "🕐 *Выберите время:*\n\n📅 Дата: {$dateLabel}",
            ['inline_keyboard' => $buttons]
        );
    }

    // ===== Confirm & Create Booking =====

    protected function confirmBooking(array $params): void
    {
        if (!$this->client) {
            \Log::channel('single')->error('Client is null in confirmBooking', [
                'callback_data' => $this->update->getCallbackQuery()->getData(),
                'from_id' => $this->update->getCallbackQuery()->getFrom()->getId(),
            ]);

            $this->telegram->sendMessage([
                'chat_id' => $this->update->getCallbackQuery()->getMessage()->getChat()->getId(),
                'text' => "❌ *Ошибка*\n\nПроизошла ошибка.\nИспользуйте /start для повторной авторизации.",
                'parse_mode' => 'Markdown',
            ]);
            return;
        }

        $type = $params[0] ?? '';

        $bookingData = match ($type) {
            'g' => $this->buildGameData($params),
            'locker' => $this->buildLockerData($params),
            'b' => $this->buildBothData($params),
            default => null,
        };

        if (!$bookingData) {
            return;
        }

        $booking = BookingRequest::create([
            'client_id' => $this->client->id,
            'service_type' => $bookingData['service_type'],
            'game_subscription_type' => $bookingData['game_type'],
            'locker_duration_months' => $bookingData['locker_months'],
            'total_price' => $bookingData['price'],
            'preferred_date' => $bookingData['preferred_date'],
            'preferred_time' => $bookingData['preferred_time'],
            'status' => BookingStatus::PENDING,
        ]);

        $dateTimeText = '';
        if ($bookingData['preferred_date']) {
            $dateTimeText = "\n📅 Дата: {$bookingData['preferred_date']->format('d.m.Y')}";
            if ($bookingData['preferred_time']) {
                $dateTimeText .= "\n🕐 Время: {$bookingData['preferred_time']}";
            }
        }

        $this->editMessage(
            "✅ *Запрос отправлен!*\n\n" .
            "Ваш запрос на бронирование принят.\n" .
            "Номер заявки: #{$booking->id}" .
            $dateTimeText . "\n\n" .
            "Ожидайте подтверждения от администратора.",
            null
        );

        $this->notifyAdminsAboutBooking($booking);
    }

    protected function buildGameData(array $params): array
    {
        // params: ['g', 'once/monthly', 'dayOffset', 'hour']
        $gameTypeStr = $params[1] ?? 'once';
        $dayOffset = (int) ($params[2] ?? 0);
        $hour = (int) ($params[3] ?? 10);

        $gameType = $gameTypeStr === 'once' ? GameSubscriptionType::ONCE : GameSubscriptionType::MONTHLY;
        $price = $gameTypeStr === 'once' ? Setting::getGameOncePrice() : Setting::getGameMonthlyPrice();

        return [
            'service_type' => ServiceType::GAME,
            'game_type' => $gameType,
            'locker_months' => null,
            'price' => $price,
            'preferred_date' => now()->addDays($dayOffset)->startOfDay(),
            'preferred_time' => sprintf('%02d:00', $hour),
        ];
    }

    protected function buildLockerData(array $params): array
    {
        // params: ['locker', 'months']
        $months = (int) ($params[1] ?? 1);

        return [
            'service_type' => ServiceType::LOCKER,
            'game_type' => null,
            'locker_months' => $months,
            'price' => Setting::getLockerMonthlyPrice() * $months,
            'preferred_date' => null,
            'preferred_time' => null,
        ];
    }

    protected function buildBothData(array $params): array
    {
        // params: ['b', 'o/m', 'months', 'dayOffset', 'hour']
        $gameTypeShort = $params[1] ?? 'o';
        $months = (int) ($params[2] ?? 1);
        $dayOffset = (int) ($params[3] ?? 0);
        $hour = (int) ($params[4] ?? 10);

        $gameType = $gameTypeShort === 'o' ? GameSubscriptionType::ONCE : GameSubscriptionType::MONTHLY;
        $gamePrice = $gameTypeShort === 'o' ? Setting::getGameOncePrice() : Setting::getGameMonthlyPrice();
        $lockerPrice = Setting::getLockerMonthlyPrice() * $months;

        return [
            'service_type' => ServiceType::BOTH,
            'game_type' => $gameType,
            'locker_months' => $months,
            'price' => $gamePrice + $lockerPrice,
            'preferred_date' => now()->addDays($dayOffset)->startOfDay(),
            'preferred_time' => sprintf('%02d:00', $hour),
        ];
    }

    // ===== Helpers =====

    protected function formatDayName(Carbon $date): string
    {
        $days = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
        return $days[$date->dayOfWeek];
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

        $dateTimeText = '';
        if ($booking->preferred_date) {
            $dateTimeText = "\n📅 Дата: {$booking->preferred_date->format('d.m.Y')}";
            if ($booking->preferred_time) {
                $dateTimeText .= " в {$booking->preferred_time}";
            }
        }

        $this->telegram->sendMessage([
            'chat_id' => $adminChatId,
            'text' => "🎯 *Новый запрос на бронирование*\n\n" .
                "👤 {$this->client->display_name}\n" .
                "📱 {$this->client->phone_number}\n" .
                "🏷️ {$booking->service_type->label()}\n" .
                "💰 \${$booking->total_price}" .
                $dateTimeText,
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
