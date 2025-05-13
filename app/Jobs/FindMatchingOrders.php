<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class FindMatchingOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected OrderMatcher $orderMatcher;

    public function __construct(public Order $order)
    {}

    public function handle(OrderMatcher $orderMatcher): void
    {
        $this->orderMatcher = $orderMatcher;

        $matchingOrders = $this->orderMatcher->findMatchingOrders(order: $this->order);

        if ($matchingOrders->isEmpty()) {
            return;
        }

        $jobs = $matchingOrders->map(function (Order $matchingOrder) {
            return new ProcessOrderMatch(newOrder: $this->order, matchedOrder: $matchingOrder);
        });

        Bus::chain($jobs)->dispatch();
    }
}
