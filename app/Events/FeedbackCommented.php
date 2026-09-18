<?php

namespace App\Events;

class FeedbackCommented extends ProjectBroadcast
{
    public function __construct(int $projectId, public int $feedbackId)
    {
        parent::__construct($projectId);
    }

    public function broadcastAs(): string
    {
        return 'feedback.commented';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['feedback' => $this->feedbackId];
    }
}
