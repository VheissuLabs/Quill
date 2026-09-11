<?php

namespace App\Actions\Issues;

use App\Enums\FeatureStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateIssue
{
    /**
     * Create an issue on the project with the next free number. When a feature is given the issue
     * is linked to it and the feature moves to Planned: the stakeholder wants it, the discussion stays
     * attached, and the work starts in the backlog.
     *
     * @param  array{title: string, description?: string|null, priority?: IssuePriority|null, points?: int|null, team_id?: int|null, sprint_id?: int|null, assignee_id?: int|null}  $attributes
     */
    public function handle(Project $project, User $creator, array $attributes, ?Feature $feature = null): Issue
    {
        return DB::transaction(function () use ($project, $creator, $attributes, $feature) {
            $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            $sprint = isset($attributes['sprint_id']) ? Sprint::find($attributes['sprint_id']) : null;

            $issue = $project->issues()->create([
                'number' => ($project->issues()->max('number') ?? 0) + 1,
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'priority' => $attributes['priority'] ?? IssuePriority::None,
                'points' => $attributes['points'] ?? null,
                'team_id' => $sprint ? $sprint->team_id : ($attributes['team_id'] ?? null),
                'sprint_id' => $sprint?->id,
                'assignee_id' => $attributes['assignee_id'] ?? null,
                'feature_id' => $feature?->id,
                'created_by' => $creator->id,
                'status' => $sprint ? IssueStatus::Todo : IssueStatus::Backlog,
            ]);

            $feature?->update(['status' => FeatureStatus::Planned]);

            return $issue;
        });
    }
}
