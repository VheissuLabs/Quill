<?php

namespace App\Events;

class TeamsChanged extends OrganizationBroadcast
{
    public function broadcastAs(): string
    {
        return 'teams.changed';
    }
}
