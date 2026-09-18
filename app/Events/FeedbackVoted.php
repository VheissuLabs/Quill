<?php

namespace App\Events;

class FeedbackVoted extends ProjectBroadcast
{
    public function __construct(int $projectId, public int $feedbackId)
    {
        parent::__construct($projectId);
    }

    public function broadcastAs(): string
    {
        return 'feedback.voted';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return ['feedback' => $this->feedbackId];
    }
}
