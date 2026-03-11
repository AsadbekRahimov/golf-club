<?php

namespace App\Telegram\Handlers;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
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

        match ($step) {
            'service' => $this->selectService($params[1] ?? ''),
            'game_type' => $this->selectGameType($params[1] ?? ''),
            'locker_start' => $this->selectLockerStartMonth($params[1] ?? ''),
            'locker_duration' => $this->selectLockerDuration($params[1] ?? '', $params[2] ?? ''),
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
            'locker' => $this->showLockerStartOptions(),
            'both' => $this->showBothOptions(),
            default => null,
        };
    }

    protected function showGameOptions(): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🎯 Единоразовая', 'callback_data' => 'booking:game_type:once']],
                [['text' => '📅 Ежемесячная', 'callback_data' => 'booking:game_type:monthly']],
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

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Подтвердить', 'callback_data' => "booking:confirm:game:{$type}"]],
                [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "🏌️ *Подтверждение бронирования*\n\n" .
            "Услуга: {$gameType->label()} подписка на игру\n\n" .
            "Подтвердить бронирование?",
            $keyboard
        );
    }

    protected function showLockerStartOptions(): void
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

        $now = Carbon::now();
        $buttons = [];

        // Current month (if not past 15th)
        if ($now->day <= 15) {
            $startOfMonth = $now->copy()->startOfMonth();
            $label = $startOfMonth->translatedFormat('F Y');
            $buttons[] = [['text' => "📅 {$label} (текущий)", 'callback_data' => "booking:locker_start:{$startOfMonth->format('Y-m')}"]];
        }

        // Next 3 months
        for ($i = 1; $i <= 3; $i++) {
            $month = $now->copy()->addMonths($i)->startOfMonth();
            $label = $month->translatedFormat('F Y');
            $buttons[] = [['text' => "📅 {$label}", 'callback_data' => "booking:locker_start:{$month->format('Y-m')}"]];
        }

        $buttons[] = [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']];

        $this->editMessage(
            "🗄️ *Аренда шкафа*\n\n" .
            "Доступно шкафов: {$available}\n\n" .
            "Выберите месяц начала аренды:",
            ['inline_keyboard' => $buttons]
        );
    }

    protected function selectLockerStartMonth(string $yearMonth): void
    {
        $startDate = Carbon::parse($yearMonth . '-01');
        $startLabel = $startDate->translatedFormat('F Y');

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '1 месяц', 'callback_data' => "booking:locker_duration:1:{$yearMonth}"]],
                [['text' => '3 месяца', 'callback_data' => "booking:locker_duration:3:{$yearMonth}"]],
                [['text' => '6 месяцев', 'callback_data' => "booking:locker_duration:6:{$yearMonth}"]],
                [['text' => '12 месяцев', 'callback_data' => "booking:locker_duration:12:{$yearMonth}"]],
                [['text' => '⬅️ Назад', 'callback_data' => 'booking:service:locker']],
            ],
        ];

        $this->editMessage(
            "🗄️ *Аренда шкафа*\n\n" .
            "Начало: *{$startLabel}*\n\n" .
            "Выберите срок аренды (мин. 1 месяц):",
            $keyboard
        );
    }

    protected function selectLockerDuration(string $months, string $yearMonth): void
    {
        $months = (int) $months;
        $startDate = Carbon::parse($yearMonth . '-01');
        $endDate = $startDate->copy()->addMonths($months)->subDay();

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Подтвердить', 'callback_data' => "booking:confirm:locker:{$months}:{$yearMonth}"]],
                [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "🗄️ *Подтверждение бронирования*\n\n" .
            "Услуга: Аренда шкафа\n" .
            "Срок: {$months} мес.\n" .
            "Период: {$startDate->format('d.m.Y')} — {$endDate->format('d.m.Y')}\n\n" .
            "Подтвердить бронирование?",
            $keyboard
        );
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

        $gameTypeEnum = $gameType === 'once' ? GameSubscriptionType::ONCE : GameSubscriptionType::MONTHLY;

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Подтвердить', 'callback_data' => "booking:confirm:both:{$gameType}"]],
                [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "📦 *Подтверждение бронирования*\n\n" .
            "Услуга: Комплексный пакет\n" .
            "Игра: {$gameTypeEnum->label()} подписка\n" .
            "Шкаф: 1 мес.\n\n" .
            "Подтвердить бронирование?",
            $keyboard
        );
    }

    protected function confirmBooking(array $params): void
    {
        if (!$this->client) {
            $this->sendAuthError();
            return;
        }

        $service = $params[0] ?? '';
        $option = $params[1] ?? '';
        $yearMonth = $params[2] ?? null;

        $bookingData = $this->buildBookingData($service, $option, $yearMonth);

        $booking = BookingRequest::create([
            'client_id' => $this->client->id,
            'service_type' => $bookingData['service_type'],
            'game_subscription_type' => $bookingData['game_type'],
            'locker_duration_months' => $bookingData['locker_months'],
            'locker_start_date' => $bookingData['locker_start_date'],
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

    protected function buildBookingData(string $service, string $option, ?string $yearMonth = null): array
    {
        return match ($service) {
            'game' => [
                'service_type' => ServiceType::GAME,
                'game_type' => $option === 'once' ? GameSubscriptionType::ONCE : GameSubscriptionType::MONTHLY,
                'locker_months' => null,
                'locker_start_date' => null,
            ],
            'locker' => [
                'service_type' => ServiceType::LOCKER,
                'game_type' => null,
                'locker_months' => (int) $option,
                'locker_start_date' => $yearMonth ? Carbon::parse($yearMonth . '-01') : null,
            ],
            'both' => [
                'service_type' => ServiceType::BOTH,
                'game_type' => $option === 'once' ? GameSubscriptionType::ONCE : GameSubscriptionType::MONTHLY,
                'locker_months' => 1,
                'locker_start_date' => null,
            ],
            default => [
                'service_type' => ServiceType::GAME,
                'game_type' => GameSubscriptionType::ONCE,
                'locker_months' => null,
                'locker_start_date' => null,
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
            "🏷️ {$booking->service_type->label()}\n" .
            "🕐 {$booking->created_at->format('d.m.Y H:i')}";

        if ($booking->locker_start_date) {
            $text .= "\n📅 Начало аренды: {$booking->locker_start_date->format('d.m.Y')}";
        }

        if ($booking->locker_duration_months) {
            $text .= "\n🗓 Срок: {$booking->locker_duration_months} мес.";
        }

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
