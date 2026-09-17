<?php

namespace App\Http\Controllers\Portal;

use App\Enums\FeatureStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ProjectBoardRequest;
use App\Http\Resources\FeatureResource;
use App\Http\Resources\ProjectViewerResource;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(ProjectBoardRequest $request, Organization $organization, Project $project): Response|RedirectResponse
    {
        $user = $request->user();

        $isMember = $user?->belongsToOrganization($organization) ?? false;
        $status = $request->status();
        $sort = $request->sort();
        $search = $request->search();

        $countsByStatus = $project->features()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $features = $project->features()
            ->with($isMember ? ['author', 'issue.project'] : ['author'])
            ->withVoteSummary($user)
            ->withCount(['comments' => fn ($query) => $query->public()])
            ->when($status, fn ($query) => $query->where('status', $status), fn ($query) => $query->active())
            ->when($search, fn ($query, string $term) => $query->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($sort === 'top', fn ($query) => $query->orderByRaw('coalesce(score, 0) desc'))
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Feature $feature) => FeatureResource::make($feature)->withIssue($isMember)->resolve());

        return Inertia::render('portal/Project', [
            'organization' => ['name' => $organization->name, 'slug' => $organization->slug, 'logoUrl' => $organization->logoUrl()],
            'project' => [
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'visibility' => $project->visibility->value,
            ],
            'features' => $features,
            'filters' => ['status' => $status?->value, 'sort' => $sort, 'search' => $search],
            'statuses' => collect(FeatureStatus::cases())->map(fn (FeatureStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($countsByStatus[$status->value] ?? 0),
            ]),
            'activeCount' => (int) collect(FeatureStatus::active())->sum(fn (FeatureStatus $status) => $countsByStatus[$status->value] ?? 0),
            'viewer' => ProjectViewerResource::make($project)->for($user),
        ]);
    }
}
