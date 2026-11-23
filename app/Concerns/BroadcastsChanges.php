<?php

namespace App\Concerns;

use App\Events\OrganizationBroadcast;
use App\Events\ProjectBroadcast;

trait BroadcastsChanges
{
    public static function bootBroadcastsChanges(): void
    {
        static::created(fn (self $model) => $model->broadcastChange());
        static::updated(fn (self $model) => $model->broadcastChange());
        static::deleted(fn (self $model) => $model->broadcastChange());
    }

    public function broadcastChange(): void
    {
        broadcast($this->changeBroadcast())->toOthers();
    }

    abstract public function changeBroadcast(): OrganizationBroadcast|ProjectBroadcast;
}
