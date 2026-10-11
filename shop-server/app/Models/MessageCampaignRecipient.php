<?php

namespace App\Models;

use App\Enums\MessageSendStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageCampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'email',
        'phone',
        'variables',
        'status',
        'error',
        'sent_at',
        'provider_ref',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'status' => MessageSendStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MessageCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MessageCampaign::class, 'campaign_id');
    }
}
