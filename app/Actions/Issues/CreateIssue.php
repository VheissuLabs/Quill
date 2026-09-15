<?php

namespace App\Actions\Issues;

use App\Enums\FeatureStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateIssue
{
    public function handle(
        Project $project,
        User $creator,
        string $title,
        ?string $description = null,
        IssuePriority $priority = IssuePriority::None,
        ?int $points = null,
        ?Team $team = null,
        ?Sprint $sprint = null,
        ?User $assignee = null,
        ?Feature $feature = null,
    ): Issue {
        return DB::transaction(function () use ($project, $creator, $title, $description, $priority, $points, $team, $sprint, $assignee, $feature) {
            $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            $issue = $project->issues()->create([
                'number' => ($project->issues()->max('number') ?? 0) + 1,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'points' => $points,
                'team_id' => $sprint ? $sprint->teamId() : $team?->getKey(),
                'sprint_id' => $sprint?->getKey(),
                'assignee_id' => $assignee?->getKey(),
                'feature_id' => $feature?->getKey(),
                'created_by' => $creator->getKey(),
                'status' => $sprint ? IssueStatus::Todo : IssueStatus::Backlog,
            ]);

            $feature?->update(['status' => FeatureStatus::Planned]);

            return $issue;
        });
    }
}
