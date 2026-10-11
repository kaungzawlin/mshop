<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'is_secret',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDecodedValue(): mixed
    {
        $raw = $this->attributes['value'] ?? null;

        if ($raw === null) {
            return null;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        if ($this->is_secret && is_string($decoded) && $decoded !== '') {
            try {
                return Crypt::decryptString($decoded);
            } catch (\Throwable) {
                return $decoded;
            }
        }

        return $decoded;
    }

    public function setDecodedValue(mixed $value): void
    {
        if ($this->is_secret && is_string($value) && $value !== '') {
            $this->attributes['value'] = json_encode(Crypt::encryptString($value));

            return;
        }

        $this->attributes['value'] = json_encode($value);
    }
}
