<?php

namespace Database\Seeders\Concerns;

use App\Models\Organization;
use App\Models\User;
use Spatie\Activitylog\Support\CauserResolver;

trait AttributesActivity
{
    /**
     * Seed as a real person so the activity log reads like a used app rather than
     * a wall of "System". Restores the previous causer afterwards.
     *
     * @template TReturn
     *
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    protected function causedBy(?User $causer, callable $callback): mixed
    {
        return app(CauserResolver::class)->withCauser($causer, fn () => $callback());
    }

    protected function ownerOf(Organization $organization): ?User
    {
        $owner = $organization->owner();

        return $owner instanceof User ? $owner : null;
    }
}
