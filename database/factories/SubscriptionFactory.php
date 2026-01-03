<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionType;
use App\Models\Client;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory()->approved(),
            'subscription_type' => SubscriptionType::GAME_ONCE,
            'start_date' => now(),
            'end_date' => null,
            'price' => 50.00,
            'status' => SubscriptionStatus::ACTIVE,
        ];
    }

    public function gameMonthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_type' => SubscriptionType::GAME_MONTHLY,
            'end_date' => now()->addMonth(),
            'price' => 200.00,
        ]);
    }

    public function locker(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_type' => SubscriptionType::LOCKER,
            'end_date' => now()->addMonth(),
            'price' => 10.00,
        ]);
    }

    public function expiring(int $days = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => now()->addDays($days),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::EXPIRED,
            'end_date' => now()->subDay(),
        ]);
    }
}
