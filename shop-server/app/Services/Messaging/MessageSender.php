<?php

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageSendStatus;
use App\Models\MessageCampaign;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\Settings\RuntimeConfigResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MessageSender
{
    public function __construct(
        private RuntimeConfigResolver $resolver,
        private VariableRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     */
    public function sendTemplate(
        MessageTemplate $template,
        string $to,
        array $variables = [],
        ?MessageCampaign $campaign = null,
    ): MessageLog {
        $allowed = is_array($template->variables) ? $template->variables : $this->renderer->extract($template->body);
        $subject = $template->subject
            ? $this->renderer->render($template->subject, $variables, $allowed)
            : null;
        $body = $this->renderer->render($template->body, $variables, $allowed);

        return $this->sendRaw(
            channel: $template->channel,
            to: $to,
            body: $body,
            subject: $subject,
            template: $template,
            campaign: $campaign,
            meta: ['variables' => $variables],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function sendRaw(
        MessageChannel $channel,
        string $to,
        string $body,
        ?string $subject = null,
        ?MessageTemplate $template = null,
        ?MessageCampaign $campaign = null,
        array $meta = [],
    ): MessageLog {
        $group = $channel === MessageChannel::Email ? 'mail' : 'sms';

        if (! $this->resolver->enabled($group)) {
            return MessageLog::query()->create([
                'channel' => $channel,
                'template_id' => $template?->id,
                'campaign_id' => $campaign?->id,
                'to_address' => $to,
                'subject' => $subject,
                'body_rendered' => $body,
                'status' => MessageSendStatus::Failed,
                'provider' => $group,
                'error' => "{$group} setting is disabled",
                'meta' => $meta,
            ]);
        }

        $log = MessageLog::query()->create([
            'channel' => $channel,
            'template_id' => $template?->id,
            'campaign_id' => $campaign?->id,
            'to_address' => $to,
            'subject' => $subject,
            'body_rendered' => $body,
            'status' => MessageSendStatus::Queued,
            'provider' => $channel === MessageChannel::Email
                ? ($this->resolver->get('mail', 'driver') ?? 'log')
                : ($this->resolver->get('sms', 'provider') ?? 'log'),
            'meta' => $meta,
        ]);

        try {
            if ($channel === MessageChannel::Email) {
                $this->sendEmail($to, $subject ?? 'mshop', $body);
            } else {
                $this->sendSms($to, $body);
            }

            $log->status = MessageSendStatus::Sent;
            $log->provider_ref = 'local-'.uniqid();
            $log->save();
        } catch (\Throwable $e) {
            $log->status = MessageSendStatus::Failed;
            $log->error = $e->getMessage();
            $log->save();
        }

        return $log->fresh();
    }

    private function sendEmail(string $to, string $subject, string $body): void
    {
        Mail::html($body, function ($message) use ($to, $subject): void {
            $message->to($to)->subject($subject);
        });
    }

    private function sendSms(string $to, string $body): void
    {
        $provider = $this->resolver->get('sms', 'provider', 'log');

        if ($provider === 'log') {
            Log::info('SMS send', ['to' => $to, 'body' => $body]);

            return;
        }

        Log::info('SMS send via provider', [
            'provider' => $provider,
            'to' => $to,
            'body' => $body,
            'sender' => $this->resolver->get('sms', 'sender'),
        ]);
    }
}
