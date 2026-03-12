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
            'subscription_type' => SubscriptionType::LOCKER,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'status' => SubscriptionStatus::ACTIVE,
        ];
    }

    public function locker(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_type' => SubscriptionType::LOCKER,
            'end_date' => now()->addMonth(),
        ]);
    }

    public function training(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_type' => SubscriptionType::TRAINING,
            'end_date' => now()->addMonth(),
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
