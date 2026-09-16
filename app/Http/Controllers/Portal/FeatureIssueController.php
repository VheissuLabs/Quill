<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Issues\CreateIssue;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FeatureIssueController extends Controller
{
    public function store(Organization $organization, Project $project, Feature $feature, CreateIssue $createIssue): RedirectResponse
    {
        abort_if($feature->issue()->exists(), 409, __('This feature has already been converted to an issue.'));

        $issue = $createIssue->handle($project, [
            'title' => $feature->title,
            'description' => $feature->description,
            'created_by' => auth()->user()->getKey(),
        ], $feature);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Added to the backlog as :reference.', ['reference' => $issue->reference()])]);

        return to_route('issues.show', ['current_organization' => $organization, 'issue' => $issue]);
    }
}
