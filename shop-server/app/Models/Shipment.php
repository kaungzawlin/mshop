<?php

namespace App\Models;

use App\Enums\DeliveryMode;
use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'order_id',
        'seller_user_id',
        'rider_id',
        'delivery_mode',
        'status',
        'fee',
        'cod_custody',
        'eta_at',
        'attempt_log',
        'pod_refs',
        'timeline',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_mode' => DeliveryMode::class,
            'status' => ShipmentStatus::class,
            'fee' => 'integer',
            'cod_custody' => 'boolean',
            'eta_at' => 'datetime',
            'attempt_log' => 'array',
            'pod_refs' => 'array',
            'timeline' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
