<?php

namespace App\Events;

class ProjectsChanged extends OrganizationBroadcast
{
    public function broadcastAs(): string
    {
        return 'projects.changed';
    }
}
