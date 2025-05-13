<?php

namespace App\DTO;

class OrderMatcherDto
{
    public function __construct(
        public float $amountGram,
        public int $buyerId,
        public int $buyOrderId,
        public int $sellerId,
        public int $sellOrderId,
        public int $pricePerGram,
        public int $fee,
    )
    {}
}
