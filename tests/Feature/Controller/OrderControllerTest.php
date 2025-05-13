<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('lists orders for authenticated user', function () {

    Order::factory()->count(3)->create(['user_id' => $this->user->id]);


    Order::factory()->count(2)->create();

    $response = $this->getJson(route('api.v1.orders.index'));

    // Check if the response has the 'data' key
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'status',
                    'amount_gram',
                    'remaining_amount_gram',
                    'price_per_gram',
                    'created_at',
                ]
            ],
            'links',
            'meta'
        ]);

    // Now assert the count
    $this->assertCount(3, $response->json('data'));
});


test('creates a new order', function () {
    $data = [
        'type' => OrderType::BUY->value,
        'amount' => 10.5,
        'price_per_gram' => 50,
    ];

    $idempotencyKey = 'unique-123';

    $response = $this->withHeaders([
        'Idempotency-Key' => $idempotencyKey,
    ])->postJson(route('api.v1.orders.store'), $data);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'status',
                'amount_gram',
                'remaining_amount_gram',
                'price_per_gram',
                'created_at',
            ]
        ]);

    $this->assertDatabaseHas('orders', [
        'user_id' => $this->user->id,
        'type' => OrderType::BUY->value,
        'amount_gram' => 10.5,
        'price_per_gram' => 500,
        'status' => OrderStatus::OPEN->value,
    ]);
});

test('shows order details', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id]);

    $response = $this->getJson(route('api.v1.orders.show', $order));

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $order->id,
                'type' => $order->type->value,
                'status' => $order->status->value,
                'amount_gram' => $order->amount_gram,
                'remaining_amount_gram' => $order->remaining_amount_gram,
                'price_per_gram' => $order->price_per_gram,
            ]
        ]);
});

test('shows order with transactions', function () {

    $buyOrder = Order::factory()->create([
        'user_id' => $this->user->id,
        'type' => OrderType::BUY,
    ]);

    $seller = User::factory()->create();

    $sellOrder = Order::factory()->create([
        'user_id' => $seller->id,
        'type' => OrderType::SELL,
    ]);

    $transaction = Transaction::factory()->create([
        'buy_order_id' => $buyOrder->id,
        'sell_order_id' => $sellOrder->id,
        'buyer_id' => $this->user->id,
        'seller_id' => $seller->id,
    ]);

    $response = $this->getJson(route('api.v1.orders.show', $buyOrder));

    $response->assertOk()
        ->assertJsonPath('data.id', $buyOrder->id)
        ->assertJsonPath('data.transactions.0.id', $transaction->id)
        ->assertJsonPath('data.transactions.0.seller.id', $seller->id);
});

test('forbids viewing other users orders', function () {
    $otherUser = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->getJson(route('api.v1.orders.show', $order));

    $response->assertForbidden();
});

test('validates order creation', function () {
    $idempotencyKey = 'unique-123';

    $response = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson(route('api.v1.orders.store'), [
            'type' => 'invalid',
            'amount' => 0,
            'price_per_gram' => 0,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'type' => 'The selected type is invalid.',
            'amount' => 'The amount field must be at least 0.01.',
            'price_per_gram' => 'The price per gram field must be at least 1.',
        ]);
});

test('requires authentication', function () {
    auth()->logout();

    $response = $this->getJson(route('api.v1.orders.index'));

    $response->assertUnauthorized();
});

describe('idempotency', function () {
    test('returns same response for identical requests with same idempotency key', function () {
        $data = [
            'type' => OrderType::SELL->value,
            'amount' => 5.5,
            'price_per_gram' => 45,
        ];

        $idempotencyKey = 'test-key-123';

        // First request
        $firstResponse = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson(route('api.v1.orders.store'), $data);

        $firstResponse->assertCreated();
        $firstOrder = $firstResponse->json('data');

        // Second identical request
        $secondResponse = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson(route('api.v1.orders.store'), $data);

        $secondResponse->assertCreated()
            ->assertJson(['data' => $firstOrder]);

        // Verify only one order was created
        expect(Order::query()->count())->toBe(1);
    });

    test('rejects request without idempotency key for POST', function () {
        $data = [
            'type' => OrderType::SELL->value,
            'amount' => 5.5,
            'price_per_gram' => 45,
        ];

        $response = $this->postJson(route('api.v1.orders.store'), $data);

        $response->assertStatus(500);

    });

    test('allows different requests with same idempotency key', function () {
        $idempotencyKey = 'test-key-456';

        // First request
        $firstResponse = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson(route('api.v1.orders.store'), [
            'type' => OrderType::BUY->value,
            'amount' => 10,
            'price_per_gram' => 50,
        ]);

        $firstResponse->assertCreated();

        // Second different request
        $secondResponse = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson(route('api.v1.orders.store'), [
            'type' => OrderType::SELL->value,
            'amount' => 5,
            'price_per_gram' => 45,
        ]);

        $secondResponse->assertStatus(201)
            ->assertHeader('idempotency-relayed', $idempotencyKey)
            ->assertJsonPath('data.type', OrderType::BUY->value)
            ->assertJsonPath('data.amount_gram', 10);
    });
});

describe('cancel order', function () {
    test('cancels an open order', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::OPEN,
        ]);

        $response = $this->postJson(route('api.v1.orders.cancel', $order));

        $response->assertOk()
            ->assertJson(['message' => 'Order cancelled successfully.']);

        expect(Order::query()->find($order->id)->status)->toBe(OrderStatus::CANCELLED);
    });

    test('forbids cancelling other users orders', function () {
        $otherUser = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $otherUser->id,
            'status' => OrderStatus::OPEN,
        ]);

        $response = $this->postJson(route('api.v1.orders.cancel', $order));

        $response->assertForbidden();
    });

    test('cannot cancel non-open order', function () {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => OrderStatus::CANCELLED,
        ]);

        $response = $this->postJson(route('api.v1.orders.cancel', $order));

        $response->assertStatus(400)
            ->assertJson(['message' => 'Order cannot be cancelled.']);
    });
});
