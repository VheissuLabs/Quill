<?php

namespace App\Http\Controllers;

use App\Enums\FeatureStatus;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Organization $currentOrganization): Response
    {
        $user = $request->user();
        $featureVotingEnabled = $user->can('viewAny', [Project::class, $currentOrganization]);

        $pendingInvitations = OrganizationInvitation::query()
            ->with(['inviter', 'organization'])
            ->whereIn(DB::raw('LOWER(email)'), $user->verifiedEmailAddresses())
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (OrganizationInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'organization' => [
                    'name' => $invitation->organization->name,
                    'slug' => $invitation->organization->slug,
                ],
            ]);

        $projects = collect();
        $recentFeatures = collect();
        $visibleProjectIds = collect();

        if ($featureVotingEnabled) {
            $visibleProjects = $currentOrganization->projects()->visibleTo($user);
            $visibleProjectIds = $visibleProjects->pluck('projects.id');

            $projects = $visibleProjects
                ->with('client')
                ->withCount([
                    'features',
                    'features as open_features_count' => fn ($query) => $query->where('status', FeatureStatus::Open),
                ])
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
                    'openFeaturesCount' => $project->open_features_count,
                ]);

            $recentFeatures = Feature::query()
                ->whereIn('project_id', $visibleProjectIds)
                ->with('project')
                ->withCount(['votes' => fn ($query) => $query->where('direction', 1)])
                ->latest()
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn (Feature $feature) => [
                    'id' => $feature->id,
                    'title' => $feature->title,
                    'status' => $feature->status->value,
                    'statusLabel' => $feature->status->label(),
                    'votesCount' => $feature->votes_count,
                    'createdAt' => $feature->created_at->toISOString(),
                    'project' => [
                        'name' => $feature->project->name,
                        'slug' => $feature->project->slug,
                    ],
                ]);
        }

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'projects' => $projects,
            'recentFeatures' => $recentFeatures,
            'stats' => [
                'projectsCount' => $projects->count(),
                'openFeaturesCount' => $projects->sum('openFeaturesCount'),
                'votesCount' => Vote::query()->where('direction', 1)->whereHas('feature', fn ($query) => $query->whereIn('project_id', $visibleProjectIds))->count(),
            ],
            'permissions' => $user->toOrganizationPermissions($currentOrganization),
        ]);
    }
}
