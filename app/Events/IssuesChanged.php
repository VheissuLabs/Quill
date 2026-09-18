<?php

namespace App\Events;

class IssuesChanged extends OrganizationBroadcast
{
    /** @param  array<int, int>  $issueIds */
    public function __construct(int $organizationId, public array $issueIds)
    {
        parent::__construct($organizationId);
    }

    public function broadcastAs(): string
    {
        return 'issues.changed';
    }

    /** @return array<string, array<int, int>> */
    public function broadcastWith(): array
    {
        return ['issues' => $this->issueIds];
    }
}
