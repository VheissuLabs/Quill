<?php

namespace App\Actions\Issues;

use App\Enums\FeatureStatus;
use App\Enums\IssueStatus;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class CreateIssue
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(Project $project, array $attributes, ?Feature $feature = null): Issue
    {
        return DB::transaction(function () use ($project, $attributes, $feature): Issue {
            $project = Project::whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            $issue = $project->issues()->make($attributes);

            $issue->number = ($project->issues()->max('number') ?? 0) + 1;
            $issue->feature_id = $feature?->getKey();
            $issue->team_id = $issue->sprint?->teamId() ?? $issue->team_id;
            $issue->status = $issue->sprint ? IssueStatus::Todo : IssueStatus::Backlog;

            $issue->save();

            $feature?->update(['status' => FeatureStatus::Planned]);

            return $issue;
        });
    }
}
