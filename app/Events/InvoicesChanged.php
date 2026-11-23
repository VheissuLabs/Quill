<?php

namespace App\Events;

class InvoicesChanged extends OrganizationBroadcast
{
    public function broadcastAs(): string
    {
        return 'invoices.changed';
    }
}
