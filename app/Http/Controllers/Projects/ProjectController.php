<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectVisibility;
use App\Events\ClientsChanged;
use App\Events\FeedbackChanged;
use App\Events\ProjectsChanged;
use App\Events\TeamsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ProjectFormResource;
use App\Http\Resources\ProjectListResource;
use App\Http\Resources\TeamResource;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Organization $currentOrganization): Response
    {
        return Inertia::render('projects/Index', [
            'projects' => FeedbackChanged::alsoRefresh(ProjectsChanged::live(ProjectListResource::collection($projects = $currentOrganization->projects()->visibleTo(auth()->user())->with('client')->withCount('feedback')->orderBy('name')->get()), $currentOrganization->id, ClientsChanged::class), $projects->modelKeys()),
            'clients' => ClientsChanged::live(ClientResource::collection($currentOrganization->clients()->orderBy('name')->get()), $currentOrganization->id),
            'visibilities' => ProjectVisibility::options(),
        ]);
    }

    public function store(ProjectRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $project = $currentOrganization->projects()->make();
        $project->saveWithTeams($request->safe()->except('team_ids'), $request->validated('team_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.edit', ['project' => $project->slug]);
    }

    public function edit(Organization $currentOrganization, Project $project): Response
    {
        return Inertia::render('projects/Edit', [
            'project' => ProjectFormResource::make($project),
            'clients' => ClientsChanged::live(ClientResource::collection($currentOrganization->clients()->orderBy('name')->get()), $currentOrganization->id),
            'teams' => TeamsChanged::live(TeamResource::collection($currentOrganization->teams()->orderBy('name')->get()), $currentOrganization->id),
            'visibilities' => ProjectVisibility::options(),
            'billing' => $currentOrganization->allowsInvoiceManagementBy(auth()->user()) ? ['rate' => $project->billing_rate] : null,
            'documents' => ProjectsChanged::live(fn () => DocumentResource::collection($project->documents()->with(['uploader', 'organization'])->get())->resolve(), $currentOrganization->id),
        ]);
    }

    public function update(ProjectRequest $request, Organization $currentOrganization, Project $project): RedirectResponse
    {
        $project->saveWithTeams($request->safe()->except('team_ids'), $request->validated('team_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('projects.edit', ['project' => $project->slug]);
    }

    public function destroy(Organization $currentOrganization, Project $project): RedirectResponse
    {
        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }
}
