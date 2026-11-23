<?php

namespace App\Events;

class OrganizationChanged extends OrganizationBroadcast
{
    public function broadcastAs(): string
    {
        return 'organization.changed';
    }
}
