<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Events\MembersChanged;
use App\Observers\OrganizationMembershipObserver;
use Database\Factories\OrganizationMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperOrganizationMembership */
#[ObservedBy(OrganizationMembershipObserver::class)]
#[UseFactory(OrganizationMembershipFactory::class)]
class OrganizationMembership extends Pivot
{
    use BroadcastsChanges, HasFactory, LogsActivity;

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

    public function changeBroadcast(): MembersChanged
    {
        return new MembersChanged($this->organization_id);
    }
}
