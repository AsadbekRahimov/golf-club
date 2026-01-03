<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Client;
use App\Models\Locker;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

class SubscriptionService
{
    public function __construct(
        protected TelegramService $telegramService
    ) {}

    public function create(
        Client $client,
        SubscriptionType $type,
        float $price,
        ?\Carbon\Carbon $endDate = null,
        ?int $bookingRequestId = null,
        ?Locker $locker = null
    ): Subscription {
        return Subscription::create([
            'client_id' => $client->id,
            'booking_request_id' => $bookingRequestId,
            'subscription_type' => $type,
            'locker_id' => $locker?->id,
            'start_date' => now(),
            'end_date' => $endDate,
            'price' => $price,
            'status' => SubscriptionStatus::ACTIVE,
        ]);
    }

    public function cancel(Subscription $subscription, User $admin, ?string $reason = null): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $admin->id,
            'cancellation_reason' => $reason,
        ]);

        if ($subscription->locker_id) {
            $subscription->locker->release();
        }
    }

    public function extend(Subscription $subscription, int $months): Subscription
    {
        $newEndDate = $subscription->end_date
            ? $subscription->end_date->addMonths($months)
            : now()->addMonths($months);

        $additionalPrice = $subscription->isLocker()
            ? Setting::getLockerMonthlyPrice() * $months
            : Setting::getGameMonthlyPrice() * $months;

        $subscription->update([
            'end_date' => $newEndDate,
            'price' => $subscription->price + $additionalPrice,
        ]);

        return $subscription->fresh();
    }

    public function checkExpiring(): Collection
    {
        $daysBefore = Setting::getNotificationDaysBefore();

        $expiring = Subscription::with('client')
            ->active()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [
                now()->toDateString(),
                now()->addDays($daysBefore)->toDateString(),
            ])
            ->get();

        foreach ($expiring as $subscription) {
            $this->telegramService->notifySubscriptionExpiring(
                $subscription->client,
                $subscription->subscription_type->label(),
                $subscription->days_remaining ?? 0
            );
        }

        return $expiring;
    }

    public function processExpired(): int
    {
        $expired = Subscription::active()
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->get();

        $count = 0;

        foreach ($expired as $subscription) {
            $subscription->expire();
            $count++;
        }

        return $count;
    }

    public function getClientSubscriptions(Client $client): Collection
    {
        return $client->activeSubscriptions()
            ->with('locker')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function hasActiveGameSubscription(Client $client): bool
    {
        return $client->activeSubscriptions()
            ->gameSubscriptions()
            ->exists();
    }

    public function hasActiveLockerSubscription(Client $client): bool
    {
        return $client->activeSubscriptions()
            ->lockerSubscriptions()
            ->exists();
    }

    public function getStatistics(): array
    {
        return [
            'active_total' => Subscription::active()->count(),
            'active_game_once' => Subscription::active()->ofType(SubscriptionType::GAME_ONCE)->count(),
            'active_game_monthly' => Subscription::active()->ofType(SubscriptionType::GAME_MONTHLY)->count(),
            'active_locker' => Subscription::active()->ofType(SubscriptionType::LOCKER)->count(),
            'expiring_soon' => Subscription::expiring()->count(),
        ];
    }
}
