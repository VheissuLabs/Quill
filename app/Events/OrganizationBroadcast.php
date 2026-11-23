<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Inertia\Inertia;
use Inertia\LiveProp;

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

    public static function channel(int $organizationId): PrivateChannel
    {
        return new PrivateChannel(self::channelFor($organizationId));
    }

    /** @param  class-string<OrganizationBroadcast>  ...$alsoOn */
    public static function live(mixed $value, int $organizationId, string ...$alsoOn): LiveProp
    {
        return Inertia::live($value, on: [static::class, ...$alsoOn], channel: self::channel($organizationId));
    }

    /**
     * @param  iterable<int>  $organizationIds
     * @param  class-string<OrganizationBroadcast>  ...$alsoOn
     */
    public static function liveAcross(mixed $value, iterable $organizationIds, string ...$alsoOn): mixed
    {
        $channels = collect($organizationIds)->map(fn (int $organizationId) => self::channel($organizationId))->all();

        return $channels === [] ? $value : Inertia::live($value, on: [static::class, ...$alsoOn], channel: $channels);
    }

    public function broadcastOn(): PrivateChannel
    {
        return self::channel($this->organizationId);
    }
}
