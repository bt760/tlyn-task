<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Jobs\ProcessOrderJob;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = auth()->user();
        $orders = $user
            ->orders()
            ->latest()
            ->paginate(perPage: 10);

        return OrderResource::collection($orders);
    }

    public function store(OrderRequest $request)
    {
        /** @var User $user */
        $user = auth()->user();
        $order = $user->orders()->create($request->forSaveOrder());

        dispatch(new ProcessOrderJob(order: $order));

        return OrderResource::make($order);
    }

    public function show(Order $order): OrderResource
    {
        if (!auth()->user()->can(abilities: 'view', arguments: $order)) {
            abort(code: 403, message: 'Unauthorized action.');
        }

        return new OrderResource($order->loadTransactionsWithCounterparties());
    }

    public function cancel(Order $order): JsonResponse
    {
        if (!auth()->user()->can(abilities: 'cancel', arguments: $order)) {
            abort(code: 403, message: 'Unauthorized action.');
        }

        if (!$order->status->canCancel()) {
            return response()->json(['message' => 'Order cannot be cancelled.'], status: 400);
        }

        $order->update(['status' => OrderStatus::CANCELLED]);

        return response()->json(['message' => 'Order cancelled successfully.'], status: 200);
    }
}
