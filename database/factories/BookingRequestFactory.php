<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\GameSubscriptionType;
use App\Enums\ServiceType;
use App\Models\BookingRequest;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingRequestFactory extends Factory
{
    protected $model = BookingRequest::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory()->approved(),
            'service_type' => ServiceType::GAME,
            'game_subscription_type' => GameSubscriptionType::ONCE,
            'locker_duration_months' => null,
            'total_price' => 50.00,
            'status' => BookingStatus::PENDING,
        ];
    }

    public function forLocker(int $months = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => ServiceType::LOCKER,
            'game_subscription_type' => null,
            'locker_duration_months' => $months,
            'total_price' => 10.00 * $months,
        ]);
    }

    public function forBoth(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => ServiceType::BOTH,
            'game_subscription_type' => GameSubscriptionType::MONTHLY,
            'locker_duration_months' => 1,
            'total_price' => 210.00,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::APPROVED,
            'processed_at' => now(),
        ]);
    }

    public function paymentRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::PAYMENT_REQUIRED,
            'processed_at' => now(),
        ]);
    }
}
