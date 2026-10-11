<?php

namespace App\Models;

use App\Enums\DeliveryMode;
use App\Enums\ResellerStatus;
use Database\Factories\ResellerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model
{
    /** @use HasFactory<ResellerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_name',
        'status',
        'kyc_notes',
        'pickup_address',
        'region',
        'township',
        'payout_account',
        'default_delivery_mode',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResellerStatus::class,
            'default_delivery_mode' => DeliveryMode::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
