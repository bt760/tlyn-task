<?php

namespace App\Strategies\Fee;

interface FeeStrategyInterface
{
    public function calculateFee(float $amountInGram, int $pricePerGram): int;
}
