<?php

namespace App\Enums;

enum MessageSendStatus: string
{
    case Queued = 'queued';
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Skipped = 'skipped';
}
