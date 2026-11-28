<?php

namespace App\Concerns;

use App\Events\OrganizationBroadcast;
use App\Events\ProjectBroadcast;
use Closure;

trait BroadcastsChanges
{
    private static bool $changeBroadcastsMuted = false;

    public static function bootBroadcastsChanges(): void
    {
        static::created(fn (self $model) => $model->broadcastChange());
        static::updated(fn (self $model) => $model->broadcastChange());
        static::deleted(fn (self $model) => $model->broadcastChange());
    }

    public static function withoutBroadcastingChanges(Closure $callback): mixed
    {
        self::$changeBroadcastsMuted = true;

        try {
            return $callback();
        } finally {
            self::$changeBroadcastsMuted = false;
        }
    }

    public function broadcastChange(): void
    {
        if (self::$changeBroadcastsMuted) {
            return;
        }

        broadcast($this->changeBroadcast())->toOthers();
    }

    abstract public function changeBroadcast(): OrganizationBroadcast|ProjectBroadcast;
}
