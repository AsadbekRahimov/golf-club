<?php

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'phone_number' => '+998 ' . $this->faker->numerify('## ###-##-##'),
            'telegram_id' => $this->faker->unique()->randomNumber(9),
            'telegram_chat_id' => $this->faker->randomNumber(9),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'username' => $this->faker->userName(),
            'status' => ClientStatus::PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClientStatus::BLOCKED,
            'rejected_at' => now(),
        ]);
    }
}
