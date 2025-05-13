<?php

namespace App\Models;

use App\Casts\PriceCast;
use App\Enums\TransactionStatus;
use Carbon\CarbonInterface;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $buyer_id
 * @property-read int $seller_id
 * @property-read int $buy_order_id
 * @property-read int $sell_order_id
 * @property-read float $amount_gram
 * @property-read int $price_per_gram
 * @property-read int $fee
 * @property-read TransactionStatus $status
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $buyer
 * @property-read User $seller
 *
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'seller_id',
        'buy_order_id',
        'sell_order_id',
        'amount_gram',
        'price_per_gram',
        'fee',
        'fee',
        'status',
    ];

    protected function casts(): array
    {
        return  [
            'price_per_gram' => PriceCast::class,
            'fee' => PriceCast::class,
            'status' => TransactionStatus::class,
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'buyer_id', ownerKey: 'id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'seller_id', ownerKey: 'id');
    }

    public function buyOrder(): BelongsTo
    {
        return $this->belongsTo(related: Order::class, foreignKey: 'buy_order_id', ownerKey: 'id');
    }

    public function sellOrder(): BelongsTo
    {
        return $this->belongsTo(related: Order::class, foreignKey: 'sell_order_id', ownerKey: 'id');
    }
}
