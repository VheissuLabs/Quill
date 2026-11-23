<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Inertia\Inertia;
use Inertia\LiveProp;

abstract class ProjectBroadcast implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const CHANNEL_PREFIX = 'projects';

    public function __construct(public int $projectId) {}

    public static function channelFor(int $projectId): string
    {
        return self::CHANNEL_PREFIX.'.'.$projectId;
    }

    public static function channel(int $projectId): Channel
    {
        return new Channel(self::channelFor($projectId));
    }

    /** @param  class-string<ProjectBroadcast>  ...$alsoOn */
    public static function live(mixed $value, int $projectId, string ...$alsoOn): LiveProp
    {
        return Inertia::live($value, on: array_values([static::class, ...$alsoOn]), channel: self::channel($projectId));
    }

    /**
     * @param  iterable<int>  $projectIds
     * @param  class-string<ProjectBroadcast>  ...$alsoOn
     */
    public static function alsoRefresh(LiveProp $prop, iterable $projectIds, string ...$alsoOn): LiveProp
    {
        $channels = collect($projectIds)->map(fn (int $projectId) => self::channel($projectId))->all();

        return $channels === [] ? $prop : $prop->live(on: array_values([static::class, ...$alsoOn]), channel: $channels);
    }

    public function broadcastOn(): Channel
    {
        return self::channel($this->projectId);
    }
}
