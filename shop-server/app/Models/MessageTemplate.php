<?php

namespace App\Models;

use App\Enums\MessageChannel;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'channel',
        'code',
        'name',
        'locale',
        'subject',
        'body',
        'variables',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'variables' => 'array',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MessageCampaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(MessageCampaign::class, 'template_id');
    }

    /**
     * @return HasMany<MessageLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MessageLog::class, 'template_id');
    }
}
