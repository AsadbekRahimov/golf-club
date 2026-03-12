<?php

namespace App\Telegram\Handlers;

use App\Enums\BookingStatus;
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
            'start' => $this->selectStartMonth($params[1] ?? '', $params[2] ?? ''),
            'duration' => $this->selectDuration($params[1] ?? '', $params[2] ?? '', $params[3] ?? ''),
            'confirm' => $this->confirmBooking(array_slice($params, 1)),
            'cancel' => $this->cancelBooking(),
            default => null,
        };
    }

    protected function selectService(string $service): void
    {
        match ($service) {
            'locker' => $this->showStartOptions('locker'),
            'training' => $this->showStartOptions('training'),
            default => null,
        };
    }

    protected function showStartOptions(string $serviceType): void
    {
        if ($serviceType === 'locker') {
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
        }

        $title = $serviceType === 'locker' ? '🗄️ *Аренда шкафа*' : '🏌️ *Бронь на тренировку*';
        $extraInfo = $serviceType === 'locker' ? "\nДоступно шкафов: " . Locker::availableCount() . "\n" : '';

        $now = Carbon::now();
        $buttons = [];

        // Option 1: Start from today
        $today = $now->copy();
        $label = 'Сегодня (' . $today->format('d.m.Y') . ')';
        $buttons[] = [['text' => "📅 {$label}", 'callback_data' => "booking:start:{$serviceType}:{$today->format('Y-m-d')}"]];

        // Option 2: Start from next month
        $nextMonth = $now->copy()->addMonth()->startOfMonth();
        $label = $nextMonth->translatedFormat('F Y');
        $buttons[] = [['text' => "📅 {$label}", 'callback_data' => "booking:start:{$serviceType}:{$nextMonth->format('Y-m-d')}"]];

        // Option 3: Start from month after next
        $monthAfterNext = $now->copy()->addMonths(2)->startOfMonth();
        $label = $monthAfterNext->translatedFormat('F Y');
        $buttons[] = [['text' => "📅 {$label}", 'callback_data' => "booking:start:{$serviceType}:{$monthAfterNext->format('Y-m-d')}"]];

        $buttons[] = [['text' => '⬅️ Назад', 'callback_data' => 'booking:cancel']];

        $this->editMessage(
            "{$title}\n{$extraInfo}\nВыберите дату начала:",
            ['inline_keyboard' => $buttons]
        );
    }

    protected function selectStartMonth(string $serviceType, string $date): void
    {
        $startDate = Carbon::parse($date);
        $startLabel = $startDate->format('d.m.Y');

        $title = $serviceType === 'locker' ? '🗄️ *Аренда шкафа*' : '🏌️ *Бронь на тренировку*';

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '1 месяц', 'callback_data' => "booking:duration:{$serviceType}:1:{$date}"]],
                [['text' => '⬅️ Назад', 'callback_data' => "booking:service:{$serviceType}"]],
            ],
        ];

        $this->editMessage(
            "{$title}\n\n" .
            "Начало: *{$startLabel}*\n\n" .
            "Выберите срок:",
            $keyboard
        );
    }

    protected function selectDuration(string $serviceType, string $months, string $date): void
    {
        $months = (int) $months;
        $startDate = Carbon::parse($date);
        $endDate = $startDate->copy()->addMonths($months)->subDay();

        $title = $serviceType === 'locker' ? '🗄️ *Подтверждение аренды шкафа*' : '🏌️ *Подтверждение брони на тренировку*';
        $serviceLabel = $serviceType === 'locker' ? 'Аренда шкафа' : 'Бронь на тренировку';

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Подтвердить', 'callback_data' => "booking:confirm:{$serviceType}:{$months}:{$date}"]],
                [['text' => '❌ Отмена', 'callback_data' => 'booking:cancel']],
            ],
        ];

        $this->editMessage(
            "{$title}\n\n" .
            "Услуга: {$serviceLabel}\n" .
            "Срок: {$months} мес.\n" .
            "Период: {$startDate->format('d.m.Y')} — {$endDate->format('d.m.Y')}\n\n" .
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

        $serviceType = $params[0] ?? '';
        $months = (int) ($params[1] ?? 1);
        $date = $params[2] ?? null;

        $serviceEnum = $serviceType === 'locker' ? ServiceType::LOCKER : ServiceType::TRAINING;
        $startDate = $date ? Carbon::parse($date) : null;

        $booking = BookingRequest::create([
            'client_id' => $this->client->id,
            'service_type' => $serviceEnum,
            'locker_duration_months' => $months,
            'locker_start_date' => $startDate,
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
            $text .= "\n📅 Начало: {$booking->locker_start_date->format('d.m.Y')}";
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
