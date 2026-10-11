<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageSendStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    protected $fillable = [
        'channel',
        'template_id',
        'campaign_id',
        'direction',
        'to_address',
        'subject',
        'body_rendered',
        'status',
        'provider',
        'provider_ref',
        'error',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'status' => MessageSendStatus::class,
            'meta' => 'array',
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
     * @return BelongsTo<MessageCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MessageCampaign::class, 'campaign_id');
    }
}
