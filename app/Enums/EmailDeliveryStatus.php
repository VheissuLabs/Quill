<?php

namespace App\Enums;

enum EmailDeliveryStatus: string
{
    case Received = 'received';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
