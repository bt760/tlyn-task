<?php

namespace App\Jobs;

use App\DTO\OrderMatcherDto;
use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\OrderMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessOrderMatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [5, 15, 30];


    public function __construct(
        public readonly Order $newOrder,
        public readonly Order $matchedOrder
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(OrderMatcher $orderMatcher): void
    {
        try {
            DB::transaction(function () use ($orderMatcher) {
                $this->processMatch($orderMatcher);
            });
        } catch (Throwable $e) {
            Log::error(message: "Order match processing failed", context: [
                'exception' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'new_order_id' => $this->newOrder->id,
                'matched_order_id' => $this->matchedOrder->id,
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    protected function processMatch(OrderMatcher $orderMatcher): void
    {
        [$newOrder, $matchedOrder] = $this->getLockedOrders();

        // Verify orders are still valid for matching
        if (!$this->validateOrders($newOrder, $matchedOrder)) {
            Log::info('Order match skipped - orders no longer valid', [
                'new_order_id' => $newOrder->id,
                'matched_order_id' => $matchedOrder->id,
            ]);
            return;
        }

        $matchResult = $orderMatcher->processMatch($newOrder, $matchedOrder);
        if (!$matchResult) {
            Log::info(message: 'Order match skipped - no match result', context: [
                'new_order_id' => $newOrder->id,
                'matched_order_id' => $matchedOrder->id,
            ]);
            return;
        }

        $transaction = $this->createTransaction($matchResult);
        $this->updateOrders($newOrder, $matchedOrder, $matchResult);
        $this->updateBalances($transaction);
    }

    protected function getLockedOrders(): array
    {
        return [
            Order::query()->lockForUpdate()->findOrFail($this->newOrder->id),
            Order::query()->lockForUpdate()->findOrFail($this->matchedOrder->id)
        ];
    }

    protected function validateOrders(Order $newOrder, Order $matchedOrder): bool
    {
        // Check if orders are still active and have remaining amount
        return $newOrder->status !== OrderStatus::FILLED
            && $matchedOrder->status !== OrderStatus::FILLED
            && $newOrder->remaining_amount_gram > 0
            && $matchedOrder->remaining_amount_gram > 0;
    }


    protected function createTransaction(OrderMatcherDto $matchResult): Transaction
    {
        return Transaction::query()->create([
            'buyer_id' => $matchResult->buyerId,
            'seller_id' => $matchResult->sellerId,
            'buy_order_id' => $matchResult->buyOrderId,
            'sell_order_id' => $matchResult->sellOrderId,
            'amount_gram' => $matchResult->amountGram,
            'price_per_gram' => $matchResult->pricePerGram,
            'fee' => $matchResult->fee,
            'status' => TransactionStatus::COMPLETED,
        ]);
    }

    protected function updateOrders(Order $newOrder, Order $matchedOrder, $matchResult): void
    {
        $newOrder->decrement(column: 'remaining_amount_gram', amount: $matchResult->amountGram);
        $matchedOrder->decrement(column: 'remaining_amount_gram', amount: $matchResult->amountGram);

        $this->updateOrderStatus($newOrder);
        $this->updateOrderStatus($matchedOrder);
    }

    protected function updateOrderStatus(Order $order): void
    {
        $order->update([
            'status' => $order->remaining_amount_gram <= 0
                ? OrderStatus::FILLED
                : OrderStatus::PARTIAL
        ]);
    }

    protected function updateBalances(Transaction $transaction): void
    {
        $actualPricePerGram = $transaction->getRawOriginal(key: 'price_per_gram');
        $actualFee = $transaction->getRawOriginal(key: 'fee');

        $totalPrice = $transaction->amount_gram * $actualPricePerGram;

        $buyerProfile = $transaction->buyer->profile;
        $sellerProfile = $transaction->seller->profile;

        DB::transaction(function () use ($totalPrice, $transaction, $buyerProfile, $sellerProfile, $actualFee) {
            $buyerProfile->decrement('balance_rial', $totalPrice + $actualFee);
            $buyerProfile->increment('balance_gold', $transaction->amount_gram);

            $sellerProfile->increment('balance_rial', $totalPrice - $actualFee);
            $sellerProfile->decrement('balance_gold', $transaction->amount_gram);
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Order match processing failed", [
            'exception' => $exception->getMessage(),
            'stack_trace' => $exception->getTraceAsString(),
            'new_order_id' => $this->newOrder->id,
            'matched_order_id' => $this->matchedOrder->id,
        ]);

    }
}
