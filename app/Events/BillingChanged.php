<?php

namespace App\Events;

class BillingChanged extends OrganizationBroadcast
{
    public function broadcastAs(): string
    {
        return 'billing.changed';
    }
}
