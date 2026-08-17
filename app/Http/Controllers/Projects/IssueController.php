<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreIssueRequest;
use App\Models\Client;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function show(Request $request, Project $project, Issue $issue): Response
    {
        $issue->load(['type', 'client', 'reporter']);

        return Inertia::render('issues/Show', [
            'project' => ['name' => $project->name, 'slug' => $project->slug],
            'issue' => [
                'number' => $issue->number,
                'title' => $issue->title,
                'description' => $issue->description,
                'acceptanceCriteria' => $issue->acceptance_criteria,
                'type' => $issue->type->name,
                'clientName' => $issue->client?->name,
                'reporterName' => $issue->reporter?->name,
                'isOpen' => $issue->closed_at === null,
                'createdAt' => $issue->created_at?->toFormattedDateString(),
                'fromConversation' => $issue->conversation_id !== null,
            ],
        ]);
    }

    public function store(StoreIssueRequest $request, Project $project): RedirectResponse
    {
        $issue = Issue::create([
            ...$request->validated(),
            'organization_id' => $project->organization_id,
            'project_id' => $project->id,
            'client_id' => $project->owner_type === Client::class ? $project->owner_id : null,
            'reported_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue filed.')]);

        return to_route('projects.issues.show', [$project->slug, $issue->number]);
    }
}
