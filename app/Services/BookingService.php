<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Enums\SubscriptionType;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;

class BookingService
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    public function create(
        Client $client,
        ServiceType $serviceType,
        ?GameSubscriptionType $gameType = null,
        ?int $lockerMonths = null
    ): BookingRequest {
        if (in_array($serviceType, [ServiceType::LOCKER, ServiceType::BOTH])) {
            if (Locker::availableCount() === 0) {
                throw new \Exception('Нет свободных шкафов');
            }
        }

        $price = $this->calculatePrice($serviceType, $gameType, $lockerMonths);

        $booking = BookingRequest::create([
            'client_id' => $client->id,
            'service_type' => $serviceType,
            'game_subscription_type' => $gameType,
            'locker_duration_months' => $lockerMonths,
            'total_price' => $price,
            'status' => BookingStatus::PENDING,
        ]);

        $this->telegramService->notifyAdmins(
            "🎯 *Новый запрос на бронирование*\n\n" .
            "👤 {$client->display_name}\n" .
            "📱 {$client->phone_number}\n" .
            "🏷️ {$serviceType->label()}\n" .
            "💰 \${$price}"
        );

        return $booking;
    }

    public function calculatePrice(
        ServiceType $serviceType,
        ?GameSubscriptionType $gameType = null,
        ?int $lockerMonths = null
    ): float {
        $price = 0;

        if (in_array($serviceType, [ServiceType::GAME, ServiceType::BOTH])) {
            $price += match ($gameType) {
                GameSubscriptionType::ONCE => Setting::getGameOncePrice(),
                GameSubscriptionType::MONTHLY => Setting::getGameMonthlyPrice(),
                default => 0,
            };
        }

        if (in_array($serviceType, [ServiceType::LOCKER, ServiceType::BOTH])) {
            $price += Setting::getLockerMonthlyPrice() * ($lockerMonths ?? 1);
        }

        return $price;
    }

    public function approveWithoutPayment(BookingRequest $booking, User $admin): void
    {
        $booking->update([
            'status' => BookingStatus::APPROVED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        $this->activateSubscriptions($booking);

        $details = $this->buildBookingDetails($booking);
        $this->telegramService->notifyBookingApproved($booking->client, $details);
    }

    public function requirePayment(BookingRequest $booking, User $admin): void
    {
        $booking->update([
            'status' => BookingStatus::PAYMENT_REQUIRED,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        Payment::create([
            'booking_request_id' => $booking->id,
            'client_id' => $booking->client_id,
            'amount' => $booking->total_price,
            'status' => 'pending',
        ]);

        $this->telegramService->notifyPaymentRequired($booking->client, $booking->total_price);
    }

    public function verifyPayment(Payment $payment, User $admin): void
    {
        $payment->verify($admin);

        $booking = $payment->bookingRequest;
        $booking->approve($admin);

        $this->activateSubscriptions($booking);

        $this->telegramService->notifyPaymentVerified($booking->client);
    }

    public function rejectPayment(Payment $payment, User $admin, ?string $reason = null): void
    {
        $payment->reject($admin, $reason);

        $this->telegramService->notifyPaymentRejected($payment->client, $reason);
    }

    public function reject(BookingRequest $booking, User $admin, ?string $reason = null): void
    {
        $booking->reject($admin, $reason);

        $this->telegramService->notifyBookingRejected($booking->client, $reason);
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

            $price = $booking->game_subscription_type === GameSubscriptionType::ONCE
                ? Setting::getGameOncePrice()
                : Setting::getGameMonthlyPrice();

            Subscription::create([
                'client_id' => $client->id,
                'booking_request_id' => $booking->id,
                'subscription_type' => $subscriptionType,
                'start_date' => now(),
                'end_date' => $endDate,
                'price' => $price,
                'status' => 'active',
            ]);
        }

        if ($booking->hasLocker()) {
            $locker = Locker::getFirstAvailable();
            
            if ($locker) {
                $locker->occupy();
                $months = $booking->locker_duration_months ?? 1;

                Subscription::create([
                    'client_id' => $client->id,
                    'booking_request_id' => $booking->id,
                    'subscription_type' => SubscriptionType::LOCKER,
                    'locker_id' => $locker->id,
                    'start_date' => now(),
                    'end_date' => now()->addMonths($months),
                    'price' => Setting::getLockerMonthlyPrice() * $months,
                    'status' => 'active',
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
        }

        $details .= "Стоимость: \${$booking->total_price}";

        return $details;
    }
}
