<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tier extends Model
{
    protected $fillable = [
        'code',
        'name_my',
        'name_en',
        'min_points_earned',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_points_earned' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<TierHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(TierHistory::class);
    }
}
