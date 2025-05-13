<?php

use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;
use Square1\LaravelIdempotency\Http\Middleware\IdempotencyMiddleware;

Route::middleware(['auth:sanctum'])->prefix('v1')->as('api.v1.')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders', [OrderController::class, 'store'])
        ->middleware(IdempotencyMiddleware::class)
        ->name('orders.store');

    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
});
