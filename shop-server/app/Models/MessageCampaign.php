<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\MessageChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageCampaign extends Model
{
    protected $fillable = [
        'channel',
        'template_id',
        'name',
        'status',
        'audience_type',
        'scheduled_at',
        'created_by',
        'stats',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime',
            'stats' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MessageTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<MessageCampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(MessageCampaignRecipient::class, 'campaign_id');
    }

    /**
     * @return HasMany<MessageLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MessageLog::class, 'campaign_id');
    }
}
