<?php

namespace App\Models;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperIssue */
#[UseFactory(IssueFactory::class)]
class Issue extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

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

    public function reference(): string
    {
        return "{$this->project->name} #{$this->number}";
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
