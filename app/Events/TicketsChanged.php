<?php

namespace App\Events;

class TicketsChanged extends OrganizationBroadcast
{
    public function broadcastAs(): string
    {
        return 'tickets.changed';
    }
}
