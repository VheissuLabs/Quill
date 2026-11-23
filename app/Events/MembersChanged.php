<?php

namespace App\Events;

class MembersChanged extends OrganizationBroadcast
{
    public function broadcastAs(): string
    {
        return 'members.changed';
    }
}
