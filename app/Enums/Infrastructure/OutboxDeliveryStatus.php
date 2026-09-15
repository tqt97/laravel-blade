<?php

namespace App\Enums\Infrastructure;

enum OutboxDeliveryStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
}
