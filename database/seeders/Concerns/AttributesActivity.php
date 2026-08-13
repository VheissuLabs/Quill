<?php

namespace Database\Seeders\Concerns;

use App\Models\Organization;
use App\Models\User;
use Spatie\Activitylog\Facades\Activity;

trait AttributesActivity
{
    /**
     * Seed as a real person so the activity log does not read as a wall of "System".
     *
     * @template TReturn
     *
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    protected function causedBy(?User $causer, callable $callback): mixed
    {
        return Activity::defaultCauser($causer, fn () => $callback());
    }

    protected function ownerOf(Organization $organization): ?User
    {
        $owner = $organization->owner();

        return $owner instanceof User ? $owner : null;
    }
}
