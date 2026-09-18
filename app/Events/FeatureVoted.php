<?php

namespace App\Events;

class FeatureVoted extends ProjectBroadcast
{
    public function __construct(int $projectId, public int $featureId)
    {
        parent::__construct($projectId);
    }

    public function broadcastAs(): string
    {
        return 'feature.voted';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['feature' => $this->featureId];
    }
}
