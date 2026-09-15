<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\SprintStatus;
use App\Observers\SprintObserver;
use Database\Factories\SprintFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A time box in which a team commits to a set of issues.
 *
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
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
 * @property-read Organization|Team $owner
 * @property-read Collection<int, Issue> $issues
 */
#[ObservedBy(SprintObserver::class)]
class Sprint extends Model
{
    /** @use HasFactory<SprintFactory> */
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /**
     * Get whoever runs the sprint: a team, or the organization itself when it has no teams.
     *
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the organization the sprint belongs to, whether or not a team runs it.
     */
    public function organization(): Organization
    {
        return $this->owner instanceof Team ? $this->owner->organization : $this->owner;
    }

    /**
     * Get the team running this sprint, or null when the organization runs it itself.
     */
    public function teamId(): ?int
    {
        return $this->owner instanceof Team ? $this->owner->getKey() : null;
    }

    /**
     * Get the board columns the sprint's work moves through.
     *
     * @return Collection<int, BoardColumn>
     */
    public function columns(): Collection
    {
        return $this->owner->columns;
    }

    /**
     * Resolve a scoped route binding.
     *
     * The columns belong to whoever runs the board, not to the sprint, so the default lookup
     * has no relation to follow.
     *
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        if ($childType === 'column') {
            return $this->owner->columns()->where($field ?? (new BoardColumn)->getRouteKeyName(), $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
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
