<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserProfile>
 */
class UserProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance_rial' => $this->faker->numberBetween(int1: 0, int2: 1_000_000_000),
            'balance_gold' => $this->faker->randomFloat(nbMaxDecimals: 3, min: 0, max: 1_00),
        ];
    }

    public function withZeroBalance(): UserProfileFactory|Factory
    {
        return $this->state(fn(array $attributes) => [
            'balance_rial' => 0,
            'balance_gold' => 0,
        ]);
    }
}
