<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        /** @var Transaction $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'amount_gram' => $resource->amount_gram,
            'price_per_gram' => $resource->price_per_gram,
            'fee' => $resource->fee,
            'status' => $resource->status->value,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'buyer' => $this->whenLoaded('buyer', UserResource::make($resource->buyer)),
            'seller' => $this->whenLoaded('seller', UserResource::make($resource->seller)),
        ];
    }
}
