<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperOrganizationMembership */
class OrganizationMembership extends Pivot
{
    use LogsActivity;

    protected $guarded = [];

    /** @var string */
    protected $table = 'organization_members';

    /** @var bool */
    public $incrementing = true;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('organization_members')
            ->logOnly(['organization_id', 'user_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
