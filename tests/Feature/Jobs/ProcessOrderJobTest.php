<?php

use App\Models\Order;
use App\Jobs\FindMatchingOrders;
use App\Jobs\ProcessOrderJob;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('ProcessOrderJob dispatches FindMatchingOrders job with correct order', function () {
    // Set up Queue fake to capture dispatched jobs
    Queue::fake();

    $order = Order::factory()->buy()->create([
        'remaining_amount_gram' => 10.0,
        'price_per_gram' => 50_000_000,
    ]);

    $job = new ProcessOrderJob($order);
    $job->handle();

    Queue::assertPushed(FindMatchingOrders::class, function ($job) use ($order) {
        return $job->order->id === $order->id;
    });
});

test('ProcessOrderJob passes the original order instance to FindMatchingOrders', function () {
    $order = Order::factory()->sell()->create([
        'remaining_amount_gram' => 5.0,
        'price_per_gram' => 60_000_000,
    ]);

    $dispatchedJob = null;
    Queue::fake([FindMatchingOrders::class]);

    $job = new ProcessOrderJob($order);
    $job->handle();

    Queue::assertPushed(FindMatchingOrders::class, function ($job) use ($order, &$dispatchedJob) {
        $dispatchedJob = $job;
        return true;
    });

    expect($dispatchedJob->order->id)->toBe($order->id)
        ->and($dispatchedJob->order->type)->toBe(OrderType::SELL)
        ->and((float) $dispatchedJob->order->remaining_amount_gram)->toBe(5.0)
        ->and((int) $dispatchedJob->order->price_per_gram)->toBe(60_000_000)
        ->and($dispatchedJob->order->status)->toBe(OrderStatus::OPEN);
});

test('ProcessOrderJob implements ShouldQueue interface', function () {
    $job = new ProcessOrderJob(Order::factory()->make());

    expect($job)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);

    // Check that the job uses required traits
    $traits = class_uses_recursive(ProcessOrderJob::class);
    expect($traits)->toContain(
        Illuminate\Bus\Queueable::class,
        Illuminate\Queue\InteractsWithQueue::class,
        Illuminate\Queue\SerializesModels::class
    );
});
