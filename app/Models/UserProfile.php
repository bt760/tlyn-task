<?php

namespace App\Models;

use App\Casts\PriceCast;
use Carbon\CarbonInterface;
use Database\Factories\UserProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read int $balance_rial
 * @property-read float $balance_gold
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property User $user
 */
class UserProfile extends Model
{
    /** @use HasFactory<UserProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance_rial',
        'balance_gold',
    ];


    protected function casts()
    {
        return [
            'balance_rial' => PriceCast::class,
            'balance_gold' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'user_id', ownerKey: 'id');
    }
}
