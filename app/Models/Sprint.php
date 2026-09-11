<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\SprintStatus;
use Database\Factories\SprintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Illuminate\Support\Facades\DB;

/**
 * A time box in which a team commits to a set of issues.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property SprintStatus $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $issues_count
 * @property-read int|null $done_issues_count
 * @property-read int|float|string|null $issues_sum_points
 * @property-read Team $team
 * @property-read Collection<int, Issue> $issues
 * @property-read Collection<int, BoardColumn> $columns
 */
#[Fillable(['team_id', 'name', 'starts_at', 'ends_at', 'status'])]
class Sprint extends Model
{
    /** @use HasFactory<SprintFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the team running the sprint.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the board columns of the sprint's team.
     *
     * @return HasManyThrough<BoardColumn, Team, $this>
     */
    public function columns(): HasManyThrough
    {
        return $this->hasManyThrough(BoardColumn::class, Team::class, 'id', 'team_id', 'team_id', 'id');
    }

    /**
     * Get the issues planned into the sprint.
     *
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Start the sprint.
     */
    public function start(): void
    {
        $this->update(['status' => SprintStatus::Active]);
    }

    /**
     * Complete the sprint and return unfinished issues to the backlog.
     */
    public function complete(): void
    {
        DB::transaction(function () {
            $this->issues()
                ->whereNotIn('status', [IssueStatus::Done, IssueStatus::Cancelled])
                ->update(['sprint_id' => null, 'board_column_id' => null, 'status' => IssueStatus::Backlog]);

            $this->forceFill([
                'status' => SprintStatus::Completed,
                'completed_at' => now(),
            ])->save();
        });
    }

    /**
     * Record sprint changes, including who started and completed it.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('sprints')
            ->logOnly(['name', 'status', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => SprintStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
