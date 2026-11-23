<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Events\TeamsChanged;
use App\Observers\TeamMembershipObserver;
use Database\Factories\TeamMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperTeamMembership */
#[ObservedBy(TeamMembershipObserver::class)]
#[UseFactory(TeamMembershipFactory::class)]
class TeamMembership extends Pivot
{
    use BroadcastsChanges, HasFactory, LogsActivity;

    protected $guarded = [];

    /** @var string */
    protected $table = 'team_members';

    /** @var bool */
    public $incrementing = true;

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('team_members')
            ->logOnly(['team_id', 'user_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function changeBroadcast(): ?TeamsChanged
    {
        $this->loadMissing('team');

        return $this->team ? new TeamsChanged($this->team->organization_id) : null;
    }
}
