<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Issues\CreateIssue;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FeedbackIssueController extends Controller
{
    public function store(Organization $organization, Project $project, Feedback $feedback, CreateIssue $createIssue): RedirectResponse
    {
        $issue = $createIssue->handle($project, [
            'title' => $feedback->title,
            'description' => $feedback->description,
            'created_by' => auth()->user()->getKey(),
        ], $feedback);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added to the backlog as :reference.', ['reference' => $issue->reference()])]);

        return to_route('issues.show', ['current_organization' => $organization, 'issue' => $issue]);
    }
}
