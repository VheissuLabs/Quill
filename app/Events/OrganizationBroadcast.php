<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

abstract class OrganizationBroadcast implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const CHANNEL_PREFIX = 'organizations';

    public const CHANNEL_PATTERN = self::CHANNEL_PREFIX.'.{organization}';

    public function __construct(public int $organizationId) {}

    public static function channelFor(int $organizationId): string
    {
        return self::CHANNEL_PREFIX.'.'.$organizationId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(self::channelFor($this->organizationId));
    }
}
