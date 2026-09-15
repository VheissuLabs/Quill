<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\SaveProjectRequest;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display the organization's projects the user may see.
     */
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Project::class, $currentOrganization]);

        return Inertia::render('projects/Index', [
            'projects' => $currentOrganization->projects()
                ->visibleTo($request->user())
                ->with('client')
                ->withCount('features')
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'description' => $project->description,
                    'visibility' => $project->visibility->value,
                    'visibilityLabel' => $project->visibility->label(),
                    'clientName' => $project->client?->name,
                    'featuresCount' => $project->features_count,
                ]),
            'clients' => $this->clientOptions($currentOrganization),
            'visibilities' => ProjectVisibility::options(),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function store(SaveProjectRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [Project::class, $currentOrganization]);

        $project = DB::transaction(function () use ($request, $currentOrganization) {
            $project = $currentOrganization->projects()->create($request->safe()->except('team_ids'));

            $this->syncTeams($project, $request->validated('team_ids', []));

            return $project;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.edit', ['project' => $project->slug]);
    }

    public function edit(Organization $currentOrganization, Project $project): Response
    {
        Gate::authorize('update', $project);

        return Inertia::render('projects/Edit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'visibility' => $project->visibility->value,
                'clientId' => $project->client_id,
                'teamIds' => $project->teams()->pluck('teams.id'),
            ],
            'clients' => $this->clientOptions($currentOrganization),
            'teams' => $currentOrganization->teams()
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team) => ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug]),
            'visibilities' => ProjectVisibility::options(),
        ]);
    }

    public function update(SaveProjectRequest $request, Organization $currentOrganization, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        DB::transaction(function () use ($request, $project) {
            $project->update($request->safe()->except('team_ids'));

            $this->syncTeams($project, $request->validated('team_ids', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('projects.edit', ['project' => $project->slug]);
    }

    /**
     * Delete the specified project along with its features.
     */
    public function destroy(Organization $currentOrganization, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }

    /**
     * Restrict a private project to the given teams. Public projects are never restricted.
     *
     * @param  array<int, int>  $teamIds
     */
    private function syncTeams(Project $project, array $teamIds): void
    {
        $project->teams()->sync($project->isPublic() ? [] : $teamIds);
    }

    /**
     * Get the organization's clients as options for a select input.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function clientOptions(Organization $organization): Collection
    {
        return $organization->clients()
            ->orderBy('name')
            ->get()
            ->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->name]);
    }
}
