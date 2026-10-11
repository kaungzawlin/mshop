<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    protected $fillable = [
        'reseller_id',
        'period_start',
        'period_end',
        'gross_sales',
        'commission',
        'net_payout',
        'status',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_sales' => 'integer',
            'commission' => 'integer',
            'net_payout' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Reseller, $this>
     */
    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    /**
     * @return HasMany<SettlementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SettlementLine::class);
    }
}
