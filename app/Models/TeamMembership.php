<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperTeamMembership */
class TeamMembership extends Pivot
{
    use LogsActivity;

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
}
