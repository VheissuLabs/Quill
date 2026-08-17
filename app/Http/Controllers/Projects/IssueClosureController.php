<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class IssueClosureController extends Controller
{
    public function store(Project $project, Issue $issue): RedirectResponse
    {
        $issue->close();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue closed.')]);

        return to_route('projects.issues.show', [$project->slug, $issue->number]);
    }

    public function destroy(Project $project, Issue $issue): RedirectResponse
    {
        $issue->reopen();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue reopened.')]);

        return to_route('projects.issues.show', [$project->slug, $issue->number]);
    }
}
