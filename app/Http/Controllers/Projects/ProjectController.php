<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = $user->currentOrganization;
        $team = $user->currentTeam;

        $inScope = $organization !== null
            && $team !== null
            && $team->organization_id === $organization->id;

        $projects = $inScope
            ? $team->projectsInScope()->with('owner')->orderBy('name')->get()
            : collect();

        return Inertia::render('projects/Index', [
            'teamName' => $inScope ? $team->name : null,
            'projects' => $projects->map(fn (Project $project) => [
                ...(array) $user->toUserProject($project),
                'createdAt' => $project->created_at?->toFormattedDateString(),
            ])->values(),
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        $project->load('owner', 'defaultForClients');

        return Inertia::render('projects/Show', [
            'project' => [
                ...(array) $request->user()->toUserProject($project),
                'description' => $project->description,
                'defaultForClients' => $project->defaultForClients->pluck('name')->values(),
            ],
        ]);
    }
}
