<?php

namespace App\Events;

class FeedbackChanged extends ProjectBroadcast
{
    public function __construct(int $projectId, public int $feedbackId)
    {
        parent::__construct($projectId);
    }

    public function broadcastAs(): string
    {
        return 'feedback.changed';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['feedback' => $this->feedbackId];
    }
}
