<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\OrderType;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $resource */
        $resource = $this->resource;

        $transactions = $resource->type == OrderType::BUY ? $this->whenLoaded('buyTransactions', function() use ($resource) {
            return TransactionResource::collection($resource->buyTransactions);
        }) : $this->whenLoaded('sellTransactions', function() use ($resource) {
            return TransactionResource::collection($resource->sellTransactions);
        });


        return  [
            'id' => $resource->id,
            'type' => $resource->type->value,
            'status' => $resource->status->value,
            'amount_gram' => (float) $resource->amount_gram,
            'remaining_amount_gram' => (float) $resource->remaining_amount_gram,
            'price_per_gram' => (int) $resource->price_per_gram,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'transactions' => $transactions,
        ];
    }
}
