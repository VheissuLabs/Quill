<?php

namespace App\Actions\Issues;

use App\Enums\FeedbackStatus;
use App\Enums\IssueStatus;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Project;
use DomainException;
use Illuminate\Support\Facades\DB;

class CreateIssue
{
    /** @param  array<string, mixed>  $attributes */
    public function handle(Project $project, array $attributes, ?Feedback $feedback = null): Issue
    {
        if ($feedback?->issue()->exists()) {
            throw new DomainException(__('This feedback has already been converted to an issue.'));
        }

        return DB::transaction(function () use ($project, $attributes, $feedback): Issue {
            $project = Project::whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            $issue = $project->issues()->make($attributes);

            $issue->number = ($project->issues()->max('number') ?? 0) + 1;
            $issue->feedback_id = $feedback?->getKey();
            $issue->team_id = $issue->sprint?->teamId() ?? $issue->team_id;
            $issue->status = $issue->sprint ? IssueStatus::Todo : IssueStatus::Backlog;

            $issue->save();

            $feedback?->update(['status' => FeedbackStatus::Planned]);

            return $issue;
        });
    }
}
