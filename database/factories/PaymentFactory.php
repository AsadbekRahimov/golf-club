<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\BookingRequest;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_request_id' => BookingRequest::factory()->paymentRequired(),
            'client_id' => fn (array $attributes) => BookingRequest::find($attributes['booking_request_id'])->client_id,
            'amount' => 50.00,
            'status' => PaymentStatus::PENDING,
        ];
    }

    public function withReceipt(): static
    {
        return $this->state(fn (array $attributes) => [
            'receipt_file_path' => 'receipts/test/receipt.jpg',
            'receipt_file_name' => 'receipt.jpg',
            'receipt_file_type' => 'image/jpeg',
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::VERIFIED,
            'verified_at' => now(),
        ]);
    }
}
