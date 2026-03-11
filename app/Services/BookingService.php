<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

class BookingService
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    public function create(
        Client $client,
        ServiceType $serviceType,
        ?GameSubscriptionType $gameType = null,
        ?int $lockerMonths = null,
        ?Carbon $lockerStartDate = null
    ): BookingRequest {
        if (in_array($serviceType, [ServiceType::LOCKER, ServiceType::BOTH])) {
            if (Locker::availableCount() === 0) {
                throw new \Exception('Нет свободных шкафов');
            }
        }

        $booking = BookingRequest::create([
            'client_id' => $client->id,
            'service_type' => $serviceType,
            'game_subscription_type' => $gameType,
            'locker_duration_months' => $lockerMonths,
            'locker_start_date' => $lockerStartDate,
            'status' => BookingStatus::PENDING,
        ]);

        $this->telegramService->notifyAdmins(
            "🎯 *Новый запрос на бронирование*\n\n" .
            "👤 {$client->display_name}\n" .
            "📱 {$client->phone_number}\n" .
            "🏷️ {$serviceType->label()}\n" .
            "🕐 {$booking->created_at->format('d.m.Y H:i')}"
        );

        return $booking;
    }

    public function approve(BookingRequest $booking, User $admin): void
    {
        $booking->approve($admin);

        $this->activateSubscriptions($booking);

        $details = $this->buildBookingDetails($booking);
        $this->telegramService->notifyBookingApproved($booking->client, $details);
    }

    public function reject(BookingRequest $booking, User $admin, ?string $reason = null): void
    {
        $booking->reject($admin, $reason);

        $this->telegramService->notifyBookingRejected($booking->client, $reason);
    }

    public function assignLockerFromAdmin(Client $client, Locker $locker, int $months, Carbon $startDate, User $admin): Subscription
    {
        $locker->occupy();

        return Subscription::create([
            'client_id' => $client->id,
            'subscription_type' => SubscriptionType::LOCKER,
            'locker_id' => $locker->id,
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addMonths($months),
            'status' => SubscriptionStatus::ACTIVE,
        ]);
    }

    protected function activateSubscriptions(BookingRequest $booking): void
    {
        $client = $booking->client;

        if ($booking->hasGame()) {
            $subscriptionType = $booking->game_subscription_type === GameSubscriptionType::ONCE
                ? SubscriptionType::GAME_ONCE
                : SubscriptionType::GAME_MONTHLY;

            $endDate = $subscriptionType === SubscriptionType::GAME_MONTHLY
                ? now()->addMonth()
                : null;

            Subscription::create([
                'client_id' => $client->id,
                'booking_request_id' => $booking->id,
                'subscription_type' => $subscriptionType,
                'start_date' => now(),
                'end_date' => $endDate,
                'status' => SubscriptionStatus::ACTIVE,
            ]);
        }

        if ($booking->hasLocker()) {
            $locker = Locker::getFirstAvailable();

            if ($locker) {
                $locker->occupy();
                $months = $booking->locker_duration_months ?? 1;
                $startDate = $booking->locker_start_date ?? now();

                Subscription::create([
                    'client_id' => $client->id,
                    'booking_request_id' => $booking->id,
                    'subscription_type' => SubscriptionType::LOCKER,
                    'locker_id' => $locker->id,
                    'start_date' => $startDate,
                    'end_date' => $startDate->copy()->addMonths($months),
                    'status' => SubscriptionStatus::ACTIVE,
                ]);
            }
        }
    }

    protected function buildBookingDetails(BookingRequest $booking): string
    {
        $details = "Услуга: {$booking->service_type->label()}\n";

        if ($booking->hasGame()) {
            $details .= "Тип игры: {$booking->game_subscription_type->label()}\n";
        }

        if ($booking->hasLocker()) {
            $details .= "Шкаф: на {$booking->locker_duration_months} мес.\n";

            $lockerSubscription = $booking->client
                ->activeSubscriptions()
                ->lockerSubscriptions()
                ->latest()
                ->first();

            if ($lockerSubscription?->locker) {
                $details .= "Номер шкафа: #{$lockerSubscription->locker->locker_number}\n";
            }

            if ($booking->locker_start_date) {
                $details .= "Начало аренды: {$booking->locker_start_date->format('d.m.Y')}\n";
            }
        }

        return $details;
    }
}
