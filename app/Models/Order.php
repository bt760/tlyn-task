<?php

namespace App\Models;

use App\Casts\PriceCast;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read OrderType $type
 * @property-read OrderStatus $status
 * @property-read float $amount_gram
 * @property-read float $remaining_amount_gram
 * @property-read int $price_per_gram
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<Transaction> $transactions
 *
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount_gram',
        'remaining_amount_gram',
        'price_per_gram',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'price_per_gram' => PriceCast::class,
            'amount_gram' => 'decimal:3',
            'remaining_amount_gram' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'user_id', ownerKey: 'id');
    }

    public function buyTransactions(): HasMany
    {
        return $this->hasMany(related: Transaction::class, foreignKey: 'buy_order_id', localKey: 'id');
    }

    public function sellTransactions(): HasMany
    {
        return $this->hasMany(related: Transaction::class, foreignKey: 'sell_order_id', localKey: 'id');
    }

    /**
     * Get transactions associated with this order based on its type
     */
    public function transactions(): HasMany
    {
        return $this->type === OrderType::BUY
            ? $this->buyTransactions()
            : $this->sellTransactions();
    }

    public function loadTransactionsWithCounterparties(): Order
    {
        if ($this->type === OrderType::BUY) {
            return $this->load(['buyTransactions.seller']);
        } else {
            return $this->load(['sellTransactions.buyer']);
        }
    }

}
