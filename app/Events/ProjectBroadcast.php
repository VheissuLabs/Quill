<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

abstract class ProjectBroadcast implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const CHANNEL_PREFIX = 'projects';

    public function __construct(public int $projectId) {}

    public static function channelFor(int $projectId): string
    {
        return self::CHANNEL_PREFIX.'.'.$projectId;
    }

    public function broadcastOn(): Channel
    {
        return new Channel(self::channelFor($this->projectId));
    }
}
