<?php

use App\Enums\OrderStatus;
use App\Enums\TransactionStatus;
use App\Jobs\ProcessOrderMatch;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OrderMatcher;
use App\Strategies\Fee\FeeStrategyInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pricePerGram = 50_000_000;

    $this->buyer = User::factory()->create();
    $this->seller = User::factory()->create();

    $this->buyOrder = Order::factory()->buy()->create([
        'user_id' => $this->buyer->id,
        'remaining_amount_gram' => 10,
        'price_per_gram' => $this->pricePerGram,
    ]);

    $this->sellOrder = Order::factory()->sell()->create([
        'user_id' => $this->seller->id,
        'remaining_amount_gram' => 10,
        'price_per_gram' => $this->pricePerGram,
    ]);

    $this->orderMatcher = new OrderMatcher(new class implements FeeStrategyInterface {
        public function calculateFee(float $amountInGram, int $pricePerGram): int
        {
            return 100; // Fixed fee for testing
        }
    });
});

it('processes order match successfully', function () {
    // Set initial balances
    $initialBuyerRial = 2_000_000_000;
    $initialSellerRial = 0;
    $initialBuyerGold = 0;
    $initialSellerGold = 20;

    $this->buyer->profile->update([
        'balance_rial' => $initialBuyerRial,
        'balance_gold' => $initialBuyerGold,
    ]);

    $this->seller->profile->update([
        'balance_rial' => $initialSellerRial,
        'balance_gold' => $initialSellerGold,
    ]);

    $job = new ProcessOrderMatch($this->buyOrder, $this->sellOrder);
    $job->handle($this->orderMatcher);

    $this->buyOrder->refresh();
    $this->sellOrder->refresh();

    expect((float)$this->buyOrder->remaining_amount_gram)->toBe(0.0)
        ->and($this->buyOrder->status)->toBe(OrderStatus::FILLED)
        ->and((float)$this->sellOrder->remaining_amount_gram)->toBe(0.0)
        ->and($this->sellOrder->status)->toBe(OrderStatus::FILLED);

    $transaction = Transaction::query()->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->buyer_id)->toBe($this->buyer->id)
        ->and($transaction->seller_id)->toBe($this->seller->id)
        ->and($transaction->buy_order_id)->toBe($this->buyOrder->id)
        ->and($transaction->sell_order_id)->toBe($this->sellOrder->id)
        ->and((float)$transaction->amount_gram)->toBe(10.0)
        ->and($transaction->price_per_gram)->toBe($this->pricePerGram)
        ->and($transaction->fee)->toBe(100)
        ->and($transaction->status)->toBe(TransactionStatus::COMPLETED);

    $transactionAmount = 10 * $this->pricePerGram; // amount_gram * price_per_gram
    $buyerRialDeduction = $transactionAmount + 100; // amount + fee
    $sellerRialAddition = $transactionAmount - 100; // amount - fee
    $goldTransfer = 10.0; // amount_gram

    // Assert balances were updated correctly
    $this->buyer->profile->refresh();
    $this->seller->profile->refresh();

    expect($this->buyer->profile->balance_rial)->toBe($initialBuyerRial - $buyerRialDeduction)
        ->and((float)$this->buyer->profile->balance_gold)->toBe($initialBuyerGold + $goldTransfer)
        ->and($this->seller->profile->balance_rial)->toBe($initialSellerRial + $sellerRialAddition)
        ->and((float)$this->seller->profile->balance_gold)->toBe($initialSellerGold - $goldTransfer);
});

it('skips processing when orders are already filled', function () {
    $this->buyOrder->update([
        'status' => OrderStatus::FILLED,
        'remaining_amount_gram' => 0,
    ]);

    Log::shouldReceive('info')
        ->once()
        ->with('Order match skipped - orders no longer valid', [
            'new_order_id' => $this->buyOrder->id,
            'matched_order_id' => $this->sellOrder->id,
        ]);

    $job = new ProcessOrderMatch($this->buyOrder, $this->sellOrder);
    $job->handle($this->orderMatcher);

    expect(Transaction::query()->count())->toBe(0);
});

it('handles partial matches correctly', function () {

    $this->buyOrder->update(['remaining_amount_gram' => 5]);
    $this->sellOrder->update(['remaining_amount_gram' => 10]);

    $job = new ProcessOrderMatch($this->buyOrder, $this->sellOrder);
    $job->handle($this->orderMatcher);

    // Assert orders were partially filled
    $this->buyOrder->refresh();
    $this->sellOrder->refresh();

    expect((float)$this->buyOrder->remaining_amount_gram)->toBe(0.0)
        ->and($this->buyOrder->status)->toBe(OrderStatus::FILLED)
        ->and((float)$this->sellOrder->remaining_amount_gram)->toBe(5.0)
        ->and($this->sellOrder->status)->toBe(OrderStatus::PARTIAL);

    $transaction = Transaction::query()->first();
    expect((float)$transaction->amount_gram)->toBe(5.0);
});

it('rolls back on failure during processing', function () {

    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new \Exception('Test exception'));

    Log::shouldReceive('error')
        ->once();

    $job = new ProcessOrderMatch($this->buyOrder, $this->sellOrder);

    expect(fn() => $job->handle($this->orderMatcher))
        ->toThrow(\Exception::class, 'Test exception')
        ->and(Transaction::query()->count())->toBe(0);


    $this->buyOrder->refresh();
    $this->sellOrder->refresh();

    expect((float)$this->buyOrder->remaining_amount_gram)->toBe(10.0)
        ->and((float)$this->sellOrder->remaining_amount_gram)->toBe(10.0);
});

it('logs failure in failed method', function () {
    $exception = new \Exception('Test failure');
    $job = new ProcessOrderMatch($this->buyOrder, $this->sellOrder);

    Log::shouldReceive('error')
        ->once()
        ->with('Order match processing failed', [
            'exception' => 'Test failure',
            'stack_trace' => $exception->getTraceAsString(),
            'new_order_id' => $this->buyOrder->id,
            'matched_order_id' => $this->sellOrder->id,
        ]);

    $job->failed($exception);
});

it('retries the job on failure', function () {
    $job = new ProcessOrderMatch($this->buyOrder, $this->sellOrder);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([5, 15, 30]);
});
