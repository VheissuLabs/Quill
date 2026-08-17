<?php

namespace Database\Seeders\Concerns;

use App\Models\Organization;
use App\Models\User;
use Spatie\Activitylog\Facades\Activity;

trait AttributesActivity
{
    /**
     * @template TReturn
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    protected function causedBy(?User $causer, callable $callback): mixed
    {
        return Activity::defaultCauser($causer, fn () => $callback());
    }

    protected function ownerOf(Organization $organization): ?User
    {
        return $organization->owner;
    }
}
