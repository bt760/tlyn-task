<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(nbMaxDecimals: 3, min: 0.1, max: 2);

        return [
            'buyer_id' => User::factory(),
            'seller_id' => User::factory(),
            'buy_order_id' => Order::factory()->buy(),
            'sell_order_id' => Order::factory()->sell(),
            'amount_gram' => $amount,
            'price_per_gram' => fake()->numberBetween(int1: 60_000_000, int2: 120_000_000),
            'fee' => 1000,
            'status' => TransactionStatus::COMPLETED,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => ['status' => TransactionStatus::PENDING]);
    }


    public function failed(): static
    {
        return $this->state(fn(array $attributes) => ['status' => TransactionStatus::FAILED]);
    }
}
