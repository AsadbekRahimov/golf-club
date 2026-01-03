<?php

namespace Database\Factories;

use App\Enums\LockerStatus;
use App\Models\Locker;
use Illuminate\Database\Eloquent\Factories\Factory;

class LockerFactory extends Factory
{
    protected $model = Locker::class;
    protected static int $number = 100;

    public function definition(): array
    {
        return [
            'locker_number' => str_pad(self::$number++, 3, '0', STR_PAD_LEFT),
            'status' => LockerStatus::AVAILABLE,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LockerStatus::OCCUPIED,
        ]);
    }
}
