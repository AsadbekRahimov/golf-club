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

        $subscription->update([
            'end_date' => $newEndDate,
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
        $expired = Subscription::with(['client', 'locker'])
            ->active()
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->get();

        $count = 0;

        foreach ($expired as $subscription) {
            $subscription->expire();
            
            // Notify client in Telegram
            $this->telegramService->notifySubscriptionExpired(
                $subscription->client,
                $subscription->subscription_type->label(),
                $subscription->end_date
            );
            
            // Notify admin
            $this->telegramService->notifyAdminsAboutExpiredSubscription(
                $subscription->client,
                $subscription->subscription_type->label(),
                $subscription->locker
            );
            
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

    public function hasActiveLockerSubscription(Client $client): bool
    {
        return $client->activeSubscriptions()
            ->lockerSubscriptions()
            ->exists();
    }

    public function hasActiveTrainingSubscription(Client $client): bool
    {
        return $client->activeSubscriptions()
            ->trainingSubscriptions()
            ->exists();
    }

    public function getStatistics(): array
    {
        return [
            'active_total' => Subscription::active()->count(),
            'active_locker' => Subscription::active()->ofType(SubscriptionType::LOCKER)->count(),
            'active_training' => Subscription::active()->ofType(SubscriptionType::TRAINING)->count(),
            'expiring_soon' => Subscription::expiring()->count(),
        ];
    }
}
