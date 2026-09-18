<?php

namespace App\Events;

class ModulesChanged extends OrganizationBroadcast
{
    public function broadcastAs(): string
    {
        return 'modules.changed';
    }
}
