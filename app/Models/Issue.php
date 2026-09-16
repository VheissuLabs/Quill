<?php

namespace App\Models;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Observers\IssueObserver;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperIssue */
#[ObservedBy(IssueObserver::class)]
#[UseFactory(IssueFactory::class)]
class Issue extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    protected $attributes = ['priority' => IssuePriority::None->value];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Sprint, $this> */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /** @return BelongsTo<BoardColumn, $this> */
    public function boardColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class);
    }

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function ownedBy(Builder $query, ?Team $team): void
    {
        $query->when($team, fn (Builder $query, Team $team) => $query
            ->where(fn (Builder $query) => $query->where('team_id', $team->id)->orWhereNull('team_id')));
    }

    /**
     * @param  Builder<$this>  $query
     * @param  Collection<int, int>  $projectIds
     * @param  array{status: string|null, project: string|null, assignee: string|null}  $filters
     */
    #[Scope]
    protected function onOrganizationBoard(Builder $query, Collection $projectIds, User $user, ?Team $team, array $filters): void
    {
        $query->whereIn('project_id', $projectIds)
            ->with(['project', 'assignee', 'sprint', 'team', 'boardColumn'])
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(! $filters['status'], fn (Builder $query) => $query->whereNotIn('status', [IssueStatus::Done, IssueStatus::Cancelled]))
            ->when($filters['project'], fn (Builder $query, string $slug) => $query->whereHas('project', fn (Builder $query) => $query->where('slug', $slug)))
            ->when($filters['assignee'] === 'me', fn (Builder $query) => $query->where('assignee_id', $user->id))
            ->ownedBy($team)
            ->whereNull('sprint_id')
            ->orderBy('position')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC")
            ->latest();
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return EloquentCollection<int, Activity>
     */
    public static function recentActivity(Collection $projectIds, ?Team $team, int $limit = 20): EloquentCollection
    {
        return Activity::query()
            ->where('log_name', 'issues')
            ->where('subject_type', (new self)->getMorphClass())
            ->whereIn('subject_id', self::whereIn('project_id', $projectIds)->ownedBy($team)->select('id'))
            ->with(['causer', 'subject.project'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function reference(): string
    {
        return "{$this->project->name} #{$this->number}";
    }

    /** @param  array<string, mixed>  $changes */
    public function applyChanges(array $changes): void
    {
        if (array_key_exists('sprint_id', $changes)) {
            $this->planInto($changes['sprint_id'] ? Sprint::whereKey($changes['sprint_id'])->firstOrFail() : null);
        }

        if (array_key_exists('board_column_id', $changes)) {
            $this->moveToColumn(BoardColumn::whereKey($changes['board_column_id'])->firstOrFail());
        }

        if (array_key_exists('status', $changes)) {
            $this->changeStatus(IssueStatus::from($changes['status']));
        }
    }

    public function planInto(?Sprint $sprint): void
    {
        if (! $sprint) {
            $this->update([
                'sprint_id' => null,
                'board_column_id' => null,
                'status' => $this->status->isClosed() ? $this->status : IssueStatus::Backlog,
            ]);

            return;
        }

        $columns = $sprint->columns();

        $column = $columns->firstWhere('status', $this->status)
            ?? $columns->firstWhere('status', IssueStatus::Todo)
            ?? $columns->first();

        $this->update(['sprint_id' => $sprint->id, 'team_id' => $sprint->teamId()]);

        if ($column) {
            $this->moveToColumn($column);
        }
    }

    public function moveToColumn(BoardColumn $column): void
    {
        $this->update(['board_column_id' => $column->id, 'team_id' => $column->teamId()]);

        $this->changeStatus($column->status);
    }

    public function changeStatus(IssueStatus $status): void
    {
        DB::transaction(function () use ($status) {
            $leavesBoard = in_array($status, [IssueStatus::Backlog, IssueStatus::Cancelled], true);

            $this->forceFill([
                'status' => $status,
                'board_column_id' => $leavesBoard ? null : $this->board_column_id,
                'sprint_id' => $status === IssueStatus::Cancelled ? null : $this->sprint_id,
                'completed_at' => $status === IssueStatus::Done ? ($this->completed_at ?? now()) : null,
            ])->save();

            if ($this->feature && ($featureStatus = $status->featureStatus())) {
                $this->feature->update(['status' => $featureStatus]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('issues')
            ->logOnly(['title', 'description', 'status', 'priority', 'points', 'sprint_id', 'board_column_id', 'assignee_id', 'team_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => IssueStatus::class,
            'priority' => IssuePriority::class,
            'completed_at' => 'datetime',
        ];
    }
}
