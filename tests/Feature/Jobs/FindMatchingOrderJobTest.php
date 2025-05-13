<?php

use App\Models\Order;
use App\Services\OrderMatcher;
use App\Jobs\FindMatchingOrders;
use App\Jobs\ProcessOrderMatch;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->orderMatcher = Mockery::mock(OrderMatcher::class);
    app()->instance(OrderMatcher::class, $this->orderMatcher);
});

test('FindMatchingOrders dispatches ProcessOrderMatch jobs for each matching order', function () {

    Bus::fake();


    $buyOrder = Order::factory()->buy()->create([
        'remaining_amount_gram' => 10.0,
        'price_per_gram' => 60_000_000,
    ]);

    $sellOrder1 = Order::factory()->sell()->create([
        'remaining_amount_gram' => 5.0,
        'price_per_gram' => 60_000_000,
    ]);

    $sellOrder2 = Order::factory()->sell()->create([
        'remaining_amount_gram' => 3.0,
        'price_per_gram' => 60_000_000,
    ]);


    $matchingOrders = new Collection([$sellOrder1, $sellOrder2]);

    // Mock OrderMatcher service
    $this->orderMatcher->shouldReceive('findMatchingOrders')
        ->once()
        ->with($buyOrder)
        ->andReturn($matchingOrders);

    // Execute the job
    $job = new FindMatchingOrders($buyOrder);
    $job->handle($this->orderMatcher);

    // Assert that Bus::chain was called with the correct jobs
    Bus::assertChained([
        ProcessOrderMatch::class, // First job in chain
        ProcessOrderMatch::class, // Second job in chain
    ]);

    // Verify jobs were created with correct parameters
    Bus::assertChained([
        function (ProcessOrderMatch $job) use ($buyOrder, $sellOrder1) {
            return $job->newOrder->id === $buyOrder->id &&
                $job->matchedOrder->id === $sellOrder1->id;
        },
        function (ProcessOrderMatch $job) use ($buyOrder, $sellOrder2) {
            return $job->newOrder->id === $buyOrder->id &&
                $job->matchedOrder->id === $sellOrder2->id;
        },
    ]);
});

test('FindMatchingOrders does not dispatch jobs when no matching orders found', function () {

    Bus::fake();


    $order = Order::factory()->buy()->create([
        'remaining_amount_gram' => 10.0,
        'price_per_gram' => 60_000_000,
    ]);

    // Mock OrderMatcher service to return empty collection
    $this->orderMatcher->shouldReceive('findMatchingOrders')
        ->once()
        ->with($order)
        ->andReturn(new Collection([]));

    // Execute the job
    $job = new FindMatchingOrders($order);
    $job->handle($this->orderMatcher);

    // Assert that no chains were dispatched
    Bus::assertNothingDispatched();
});

test('FindMatchingOrders accesses OrderMatcher via dependency injection', function () {

    Bus::fake();

    $order = Order::factory()->create();

    $this->orderMatcher->shouldReceive('findMatchingOrders')
        ->once()
        ->with($order)
        ->andReturn(new Collection([]));

    // Execute the job and verify the mock was used
    $job = new FindMatchingOrders($order);
    $job->handle($this->orderMatcher);

    // This test will fail if the mock wasn't called as expected
});

test('FindMatchingOrders implements ShouldQueue interface', function () {
    $job = new FindMatchingOrders(Order::factory()->make());

    // Check that the job implements ShouldQueue
    expect($job)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);

    // Check that the job uses required traits
    $traits = class_uses_recursive(FindMatchingOrders::class);
    expect($traits)->toContain(
        Illuminate\Bus\Queueable::class,
        Illuminate\Queue\InteractsWithQueue::class,
        Illuminate\Queue\SerializesModels::class
    );
});

afterEach(function () {
    Mockery::close();
});
