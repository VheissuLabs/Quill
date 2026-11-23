<?php

namespace App\Events;

class ClientsChanged extends OrganizationBroadcast
{
    public function broadcastAs(): string
    {
        return 'clients.changed';
    }
}
