<?php

namespace App\Services;

use App\DTO\OrderMatcherDto;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Strategies\Fee\FeeStrategyInterface;
use Illuminate\Database\Eloquent\Collection;

class OrderMatcher
{

    public function __construct(protected FeeStrategyInterface $feeStrategy)
    {}

    public function findMatchingOrders(Order $order): Collection
    {
        $oppositeType = $order->type === OrderType::BUY ? OrderType::SELL : OrderType::BUY;
        $requiredAmount = $order->remaining_amount_gram;
        $accumulated = 0;
        $matchedOrderIds = [];

        Order::query()
            ->where(column: 'type', operator: '=' , value: $oppositeType->value)
            ->where(column: 'price_per_gram', operator: '=', value: $order->getRawOriginal(key: 'price_per_gram'))
            ->where(column: 'remaining_amount_gram', operator: '>', value: 0)
            ->whereIn(column: 'status', values: [OrderStatus::OPEN->value, OrderStatus::PARTIAL->value])
            ->orderBy(column: 'created_at')
            ->chunk(count: 100, callback: function ($orders) use (&$accumulated, &$matchedOrderIds, $requiredAmount) {
                foreach ($orders as $order) {
                    if ($accumulated >= $requiredAmount) {
                        return false;
                    }

                    $matchedOrderIds[] = $order->id;
                    $accumulated += $order->remaining_amount_gram;
                }
            });


        return Order::query()
            ->whereIn(column: 'id', values: $matchedOrderIds)
            ->orderBy(column: 'created_at')
            ->get();
    }

    /**
     * @param Order $order1
     * @param Order $order2
     * @return OrderMatcherDto|null
     */
    public function processMatch(Order $order1, Order $order2): ?OrderMatcherDto
    {
        $amount = min($order1->remaining_amount_gram, $order2->remaining_amount_gram);

        if ($amount <= 0) {
            return null;
        }

        $buyOrder = $order1->type === OrderType::BUY ? $order1 : $order2;
        $sellOrder = $order1->type === OrderType::SELL ? $order1 : $order2;

        return new OrderMatcherDto(
            amountGram: $amount,
            buyerId: $buyOrder->user_id,
            buyOrderId: $buyOrder->id,
            sellerId: $sellOrder->user_id,
            sellOrderId: $sellOrder->id,
            pricePerGram: $order1->price_per_gram,
            fee: $this->feeStrategy->calculateFee($amount, $order1->price_per_gram),
        );
    }
}
