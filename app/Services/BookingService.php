<?php

namespace App\Services;

use App\Enums\BookingStatus;
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
        ?int $months = null,
        ?Carbon $startDate = null
    ): BookingRequest {
        if ($serviceType === ServiceType::LOCKER) {
            if (Locker::availableCount() === 0) {
                throw new \Exception('Нет свободных шкафов');
            }
        }

        $booking = BookingRequest::create([
            'client_id' => $client->id,
            'service_type' => $serviceType,
            'locker_duration_months' => $months,
            'locker_start_date' => $startDate,
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

        $this->telegramService->notifyAdmins(
            "✅ *Бронирование подтверждено*\n\n" .
            "👤 {$booking->client->display_name}\n" .
            "📱 {$booking->client->phone_number}\n" .
            "🏷️ {$booking->service_type->label()}\n" .
            "🗓 Срок: " . ($booking->locker_duration_months ?? 1) . " мес.\n" .
            "👨‍💼 Админ: {$admin->name}"
        );
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
        $months = $booking->locker_duration_months ?? 1;
        $startDate = $booking->locker_start_date ?? now();

        if ($booking->isLocker()) {
            $locker = Locker::getFirstAvailable();

            if ($locker) {
                $locker->occupy();

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

        if ($booking->isTraining()) {
            Subscription::create([
                'client_id' => $client->id,
                'booking_request_id' => $booking->id,
                'subscription_type' => SubscriptionType::TRAINING,
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addMonths($months),
                'status' => SubscriptionStatus::ACTIVE,
            ]);
        }
    }

    protected function buildBookingDetails(BookingRequest $booking): string
    {
        $details = "Услуга: {$booking->service_type->label()}\n";
        $months = $booking->locker_duration_months ?? 1;
        $details .= "Срок: {$months} мес.\n";

        if ($booking->isLocker()) {
            $lockerSubscription = $booking->client
                ->activeSubscriptions()
                ->lockerSubscriptions()
                ->latest()
                ->first();

            if ($lockerSubscription?->locker) {
                $details .= "Номер шкафа: #{$lockerSubscription->locker->locker_number}\n";
            }
        }

        if ($booking->locker_start_date) {
            $details .= "Начало: {$booking->locker_start_date->format('d.m.Y')}\n";
        }

        return $details;
    }
}
