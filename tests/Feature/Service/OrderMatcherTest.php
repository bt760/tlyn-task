<?php

use App\DTO\OrderMatcherDto;
use App\Models\Order;
use App\Services\OrderMatcher;
use App\Strategies\Fee\FeeStrategyInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {;
    $this->feeStrategy = Mockery::mock(FeeStrategyInterface::class);
    $this->orderMatcher = new OrderMatcher($this->feeStrategy);
});

test('findMatchingOrders returns matching orders for buy order', function () {
    // Create a buy order
    $buyOrder = Order::factory()->buy()->create([
        'remaining_amount_gram' => 10.0,
        'price_per_gram' => 50_000_000,
    ]);

    // Create potential matching orders
    $matchingSellOrder1 = Order::factory()->sell()->create([
        'remaining_amount_gram' => 5.0,
        'price_per_gram' => 50_000_000,
        'created_at' => now()->subMinutes(5),
    ]);

    $matchingSellOrder2 = Order::factory()->sell()->partial()->create([
        'remaining_amount_gram' => 3.0,
        'price_per_gram' => 50_000_000,
        'created_at' => now()->subMinutes(2),
    ]);

    // Non-matching orders
    Order::factory()->sell()->create([
        'remaining_amount_gram' => 2.0,
        'price_per_gram' => 55_000_000,
    ]);

    Order::factory()->sell()->partial()->create([
        'remaining_amount_gram' => 0,
        'price_per_gram' => 50_000_000,
    ]);

    Order::factory()->buy()->create([
        'remaining_amount_gram' => 5.0,
        'price_per_gram' => 50_000_000,
    ]);

    Order::factory()->sell()->filled()->create([
        'remaining_amount_gram' => 1.0,
        'price_per_gram' => 50_000_000,
    ]);

    // Find matching orders
    $matchingOrders = $this->orderMatcher->findMatchingOrders($buyOrder);

    // Assertions
    expect($matchingOrders)->toBeInstanceOf(Collection::class)
        ->and($matchingOrders)->toHaveCount(2)
        ->and($matchingOrders->pluck('id')->toArray())->toContain($matchingSellOrder1->id, $matchingSellOrder2->id)
        ->and($matchingOrders->first()->id)->toBe($matchingSellOrder1->id)
        ->and($matchingOrders->last()->id)->toBe($matchingSellOrder2->id);
});

test('findMatchingOrders returns matching orders for sell order', function () {
    $sellOrder = Order::factory()->sell()->create([
        'remaining_amount_gram' => 10.0,
        'price_per_gram' => 50_000_000,
    ]);

    $matchingBuyOrder = Order::factory()->buy()->create([
        'remaining_amount_gram' => 5.0,
        'price_per_gram' => 50_000_000, // Same price - should match
    ]);

    Order::factory()->buy()->create([
        'remaining_amount_gram' => 3.0,
        'price_per_gram' => 55_000_000, // Different price - should NOT match
    ]);

    $matchingOrders = $this->orderMatcher->findMatchingOrders($sellOrder);

    expect($matchingOrders)->toBeInstanceOf(Collection::class)
        ->and($matchingOrders)->toHaveCount(1)
        ->and($matchingOrders->first()->id)->toBe($matchingBuyOrder->id);
});

test('findMatchingOrders respects required amount limit and stops accumulating', function () {

    $buyOrder = Order::factory()->buy()->create([
        'remaining_amount_gram' => 5.0,
        'price_per_gram' => 50_000_000,
    ]);

    $matchingSellOrder1 = Order::factory()->sell()->create([
        'remaining_amount_gram' => 3.0,
        'price_per_gram' => 50_000_000,
        'created_at' => now()->subMinutes(10),
    ]);

    $matchingSellOrder2 = Order::factory()->sell()->create([
        'remaining_amount_gram' => 2.0,
        'price_per_gram' => 50_000_000,
        'created_at' => now()->subMinutes(5),
    ]);

    // This should not be included as we already accumulated 5.0 grams
    $extraSellOrder = Order::factory()->sell()->create([
        'remaining_amount_gram' => 1.0,
        'price_per_gram' => 50_000_000,
        'created_at' => now(), // Most recent
    ]);

    $matchingOrders = $this->orderMatcher->findMatchingOrders($buyOrder);

    expect($matchingOrders)->toBeInstanceOf(Collection::class)
        ->and($matchingOrders)->toHaveCount(2)
        ->and($matchingOrders->pluck('id')->toArray())->toContain($matchingSellOrder1->id, $matchingSellOrder2->id)
        ->and($matchingOrders->pluck('id')->toArray())->not->toContain($extraSellOrder->id);
});

test('findMatchingOrders returns empty collection when no matches found', function () {

    $buyOrder = Order::factory()->buy()->create([
        'remaining_amount_gram' => 10.0,
        'price_per_gram' => 50_000_000,
    ]);

    Order::factory()->sell()->create([
        'remaining_amount_gram' => 5.0,
        'price_per_gram' => 55_000_000, // Different price
    ]);

    Order::factory()->buy()->create([
        'remaining_amount_gram' => 3.0,
        'price_per_gram' => 50_000_000,
    ]);

    $matchingOrders = $this->orderMatcher->findMatchingOrders($buyOrder);

    expect($matchingOrders)->toBeInstanceOf(Collection::class)
        ->and($matchingOrders)->toBeEmpty();
});

test('processMatch returns null when no amount can be matched', function () {

    $buyOrder = Order::factory()->buy()->filled()->create([
        'remaining_amount_gram' => 0,
        'price_per_gram' => 50_000_000,
    ]);

    $sellOrder = Order::factory()->sell()->filled()->create([
        'remaining_amount_gram' => 0,
        'price_per_gram' => 50000,
    ]);


    $result = $this->orderMatcher->processMatch($buyOrder, $sellOrder);


    expect($result)->toBeNull();
});

test('processMatch returns correct DTO for buy and sell order', function () {

    $buyOrder = Order::factory()->buy()->create([
        'remaining_amount_gram' => 5.0,
        'price_per_gram' => 50_000,
    ]);

    $sellOrder = Order::factory()->sell()->create([
        'remaining_amount_gram' => 3.0, // Less than the buy order
        'price_per_gram' => 50_000,
    ]);


    $this->feeStrategy->shouldReceive('calculateFee')
        ->once()
        ->with(3.0, 50_000) // Should use the minimum amount (3.0)
        ->andReturn(15_000);


    $result = $this->orderMatcher->processMatch($buyOrder, $sellOrder);

    // Assertions
    expect($result)->toBeInstanceOf(OrderMatcherDto::class)
        ->and($result->amountGram)->toBe(3.0) // Should use the minimum amount
        ->and($result->buyOrderId)->toBe($buyOrder->id)
        ->and($result->sellOrderId)->toBe($sellOrder->id)
        ->and($result->pricePerGram)->toBe(50_000)
        ->and($result->fee)->toBe(15_000);
});

test('processMatch returns correct DTO when order types are reversed', function () {

    $sellOrder = Order::factory()->sell()->create([
        'remaining_amount_gram' => 5.0,
        'price_per_gram' => 50_000,
    ]);

    $buyOrder = Order::factory()->buy()->create([
        'remaining_amount_gram' => 2.0, // Less than the sell order
        'price_per_gram' => 50_000,
    ]);


    $this->feeStrategy->shouldReceive('calculateFee')
        ->once()
        ->with(2.0, 50_000) // Should use the minimum amount (2.0)
        ->andReturn(10_000);


    $result = $this->orderMatcher->processMatch($sellOrder, $buyOrder);

    // Assertions
    expect($result)->toBeInstanceOf(OrderMatcherDto::class)
        ->and($result->amountGram)->toBe(2.0) // Should use the minimum amount
        ->and($result->buyOrderId)->toBe($buyOrder->id) // Should correctly identify the buy order
        ->and($result->sellOrderId)->toBe($sellOrder->id) // Should correctly identify the sell order
        ->and($result->pricePerGram)->toBe(50_000)
        ->and($result->fee)->toBe(10_000);
});

afterEach(function () {
    Mockery::close();
});
