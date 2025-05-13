<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(type: OrderType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'price_per_gram' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function forSaveOrder(): array
    {
        return [
            'type' => OrderType::from(value: $this->validated(key: 'type')),
            'amount_gram' => $this->validated(key: 'amount'),
            'remaining_amount_gram' => $this->validated(key: 'amount'),
            'status' => OrderStatus::OPEN,
            'price_per_gram' => $this->validated(key: 'price_per_gram'),
        ];
    }
}
