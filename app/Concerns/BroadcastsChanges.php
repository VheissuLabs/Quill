<?php

namespace App\Concerns;

use App\Events\OrganizationBroadcast;
use App\Events\ProjectBroadcast;
use Illuminate\Database\Eloquent\Model;

trait BroadcastsChanges
{
    public static function bootBroadcastsChanges(): void
    {
        static::created(fn (Model $model) => $model->broadcastChange());
        static::updated(fn (Model $model) => $model->broadcastChange());
        static::deleted(fn (Model $model) => $model->broadcastChange());
    }

    public function broadcastChange(): void
    {
        $event = $this->changeBroadcast();

        if ($event !== null) {
            broadcast($event)->toOthers();
        }
    }

    abstract public function changeBroadcast(): OrganizationBroadcast|ProjectBroadcast|null;
}
