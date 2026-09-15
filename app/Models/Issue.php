<?php

namespace App\Models;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A unit of work on a project, numbered per project and optionally born from a feature request.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $team_id
 * @property int|null $sprint_id
 * @property int|null $board_column_id
 * @property int|null $feature_id
 * @property int|null $assignee_id
 * @property int|null $created_by
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property IssueStatus $status
 * @property IssuePriority $priority
 * @property int|null $points
 * @property int $position
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Team|null $team
 * @property-read Sprint|null $sprint
 * @property-read BoardColumn|null $boardColumn
 * @property-read Feature|null $feature
 * @property-read User|null $assignee
 * @property-read User|null $creator
 */
#[Fillable(['project_id', 'team_id', 'sprint_id', 'board_column_id', 'feature_id', 'assignee_id', 'created_by', 'number', 'title', 'description', 'status', 'priority', 'points', 'position'])]
class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the project the issue belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the team responsible for the issue.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the sprint the issue is planned into.
     *
     * @return BelongsTo<Sprint, $this>
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /**
     * Get the board column the issue sits in while planned.
     *
     * @return BelongsTo<BoardColumn, $this>
     */
    public function boardColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class);
    }

    /**
     * Get the feature request the issue was converted from.
     *
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * Get the user the issue is assigned to.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the user who created the issue.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the identifier shown in the UI, such as "GitDifferent #12".
     */
    public function reference(): string
    {
        return "{$this->project->name} #{$this->number}";
    }

    /**
     * Plan the issue into a sprint (or back to the backlog). The issue takes the sprint's team,
     * leaves the backlog when planned, and returns to it when unplanned unless already closed.
     */
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

        $column = $sprint->team->columns->firstWhere('status', $this->status)
            ?? $sprint->team->columns->firstWhere('status', IssueStatus::Todo)
            ?? $sprint->team->columns->first();

        $this->update(['sprint_id' => $sprint->id, 'team_id' => $sprint->team_id]);

        if ($column) {
            $this->moveToColumn($column);
        }
    }

    /**
     * Place the issue in a board column and take on that column's status.
     */
    public function moveToColumn(BoardColumn $column): void
    {
        $this->update(['board_column_id' => $column->id, 'team_id' => $column->team_id]);

        $this->changeStatus($column->status);
    }

    /**
     * Move the issue to a new status, keeping the completion time and the linked feature in step.
     * Backlog and cancelled issues sit in no column, and a cancelled issue leaves its sprint.
     */
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

    /**
     * Record every meaningful change to the issue with the person who made it.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('issues')
            ->logOnly(['title', 'description', 'status', 'priority', 'points', 'sprint_id', 'board_column_id', 'assignee_id', 'team_id'])
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
            'status' => IssueStatus::class,
            'priority' => IssuePriority::class,
            'completed_at' => 'datetime',
        ];
    }
}
