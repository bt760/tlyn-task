<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(nbMaxDecimals: 3, min: 0.1, max: 5);

        return [
            'user_id' => User::factory(),
            'type' => OrderType::BUY,
            'amount_gram' => $amount,
            'remaining_amount_gram' => $amount,
            'price_per_gram' => fake()->numberBetween(int1: 60_000_000, int2: 120_000_000),
            'status' => OrderStatus::OPEN,
        ];
    }

    public function buy(): static
    {
        return $this->state(fn(array $attributes) => ['type' => OrderType::BUY]);
    }

    public function sell(): static
    {
        return $this->state(fn(array $attributes) => ['type' => OrderType::SELL]);
    }

    public function partial(): static
    {
        return $this->state(fn(array $attributes) => [
            'remaining_amount_gram' => $attributes['amount_gram'] / 2,
            'status' => OrderStatus::PARTIAL,
        ]);
    }

    public function filled(): static
    {
        return $this->state(fn() => ['remaining_amount_gram' => 0, 'status' => OrderStatus::FILLED]);
    }
}
