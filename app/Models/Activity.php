<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/** @mixin IdeHelperActivity */
class Activity extends SpatieActivity
{
    use HasUuids;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param Builder<$this> $query
     * @return Builder<$this>
     */
    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Activity $activity) {
            if ($activity->organization_id !== null) {
                return;
            }

            $subject = $activity->subject;

            $activity->organization_id = match (true) {
                $subject instanceof Organization => $subject->id,
                $subject instanceof Client,
                $subject instanceof Team,
                $subject instanceof Project => $subject->organization_id,
                $subject instanceof OrganizationInvitation => $subject->organization_id,
                $subject instanceof OrganizationMembership => $subject->organization_id,
                default => null,
            };
        });
    }
}
