<?php

namespace App\Strategies\Fee;

class TieredFeeStrategy implements FeeStrategyInterface
{
    protected int $minFee = 50_000; // 50,000 Toman (500,000 Rial)
    protected int $maxFee = 5_000_000; // 5,000,000 Toman (50,000,000 Rial)

    public function calculateFee(float $amountInGram, int $pricePerGram): int
    {
        $totalPrice = $amountInGram * $pricePerGram;
        $feeRate = $this->getRate(gram: $amountInGram);
        $feeInRial = (int) round(num: $totalPrice * $feeRate / 100);

        // Apply min/max in Rial
        $minRial = $this->toRial(toman: $this->minFee);
        $maxRial = $this->toRial(toman: $this->maxFee);

        return max($minRial, min($maxRial, $feeInRial));
    }

    private function getRate(float $gram): float
    {
        if ($gram <= 1) return 2.0;    // 2% for ≤1 gram
        if ($gram <= 10) return 1.5;   // 1.5% for 1-10 grams
        return 1.0;                    // 1% for >10 grams
    }

    private function toRial(int $toman): int
    {
        return $toman * 10;
    }
}
