<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
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
            'service_type' => ServiceType::LOCKER,
            'locker_duration_months' => 1,
            'status' => BookingStatus::PENDING,
        ];
    }

    public function forLocker(int $months = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => ServiceType::LOCKER,
            'locker_duration_months' => $months,
        ]);
    }

    public function forTraining(int $months = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => ServiceType::TRAINING,
            'locker_duration_months' => $months,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::APPROVED,
            'processed_at' => now(),
        ]);
    }
}
