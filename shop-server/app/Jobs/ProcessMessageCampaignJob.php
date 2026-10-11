<?php

namespace App\Jobs;

use App\Enums\CampaignStatus;
use App\Enums\MessageSendStatus;
use App\Models\MessageCampaign;
use App\Models\MessageCampaignRecipient;
use App\Services\Messaging\MessageSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMessageCampaignJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(MessageSender $sender): void
    {
        $campaign = MessageCampaign::query()->with('template')->find($this->campaignId);

        if ($campaign === null || $campaign->status === CampaignStatus::Cancelled) {
            return;
        }

        $campaign->status = CampaignStatus::Processing;
        $campaign->save();

        $stats = [
            'total' => (int) ($campaign->stats['total'] ?? 0),
            'sent' => (int) ($campaign->stats['sent'] ?? 0),
            'failed' => (int) ($campaign->stats['failed'] ?? 0),
            'skipped' => (int) ($campaign->stats['skipped'] ?? 0),
        ];

        MessageCampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', MessageSendStatus::Pending)
            ->orderBy('id')
            ->chunkById(100, function ($recipients) use ($campaign, $sender, &$stats): void {
                foreach ($recipients as $recipient) {
                    $to = $campaign->channel->value === 'email' ? $recipient->email : $recipient->phone;

                    if (! $to) {
                        $recipient->status = MessageSendStatus::Skipped;
                        $recipient->error = 'Missing destination';
                        $recipient->save();
                        $stats['skipped']++;

                        continue;
                    }

                    $log = $sender->sendTemplate(
                        $campaign->template,
                        $to,
                        is_array($recipient->variables) ? $recipient->variables : [],
                        $campaign,
                    );

                    if ($log->status === MessageSendStatus::Sent) {
                        $recipient->status = MessageSendStatus::Sent;
                        $recipient->sent_at = now();
                        $recipient->provider_ref = $log->provider_ref;
                        $stats['sent']++;
                    } else {
                        $recipient->status = MessageSendStatus::Failed;
                        $recipient->error = $log->error;
                        $stats['failed']++;
                    }

                    $recipient->save();
                }
            });

        $campaign->stats = $stats;
        $campaign->status = ($stats['failed'] > 0 && $stats['sent'] === 0)
            ? CampaignStatus::Failed
            : CampaignStatus::Completed;
        $campaign->save();
    }
}
