<?php

namespace App\Models;

use App\Casts\PriceCast;
use App\Enums\OrderStatusEnum;
use App\Enums\OrderTypeEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read OrderTypeEnum $type
 * @property-read OrderStatusEnum $status
 * @property-read float $amount_gram
 * @property-read float $remaining_amount_gram
 * @property-read int $price_per_gram
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 *
 */
class Order extends Model
{
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
            'type' => OrderTypeEnum::class,
            'status' => OrderStatusEnum::class,
            'price_per_gram' => PriceCast::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'user_id', ownerKey: 'id');
    }
}
