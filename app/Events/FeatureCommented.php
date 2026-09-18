<?php

namespace App\Events;

class FeatureCommented extends ProjectBroadcast
{
    public function __construct(int $projectId, public int $featureId)
    {
        parent::__construct($projectId);
    }

    public function broadcastAs(): string
    {
        return 'feature.commented';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['feature' => $this->featureId];
    }
}
