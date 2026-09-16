<?php

namespace App\Http\Controllers;

use App\Enums\FeatureStatus;
use App\Enums\OrganizationCapability;
use App\Http\Resources\DashboardProjectResource;
use App\Http\Resources\PendingInvitationResource;
use App\Http\Resources\RecentFeatureResource;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Vote;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Organization $currentOrganization): Response
    {
        $user = auth()->user();
        $projects = collect();
        $recentFeatures = collect();
        $visibleProjectIds = collect();

        if ($currentOrganization->canUse(OrganizationCapability::Boards)) {
            $visibleProjectIds = $currentOrganization->projects()->visibleTo($user)->pluck('projects.id');

            $projects = DashboardProjectResource::collection($currentOrganization->projects()
                ->visibleTo($user)
                ->with('client')
                ->withCount(['features', 'features as open_features_count' => fn ($query) => $query->where('status', FeatureStatus::Open)])
                ->orderBy('name')
                ->get())->resolve();

            $recentFeatures = RecentFeatureResource::collection(Feature::whereIn('project_id', $visibleProjectIds)
                ->with('project')
                ->withCount(['votes' => fn ($query) => $query->where('direction', 1)])
                ->latest()
                ->orderByDesc('id')
                ->limit(8)
                ->get());
        }

        return Inertia::render('Dashboard', [
            'pendingInvitations' => PendingInvitationResource::collection(OrganizationInvitation::pendingFor($user)->get()),
            'projects' => $projects,
            'recentFeatures' => $recentFeatures,
            'stats' => [
                'projectsCount' => count($projects),
                'openFeaturesCount' => collect($projects)->sum('openFeaturesCount'),
                'votesCount' => Vote::where('direction', 1)->whereHas('feature', fn ($query) => $query->whereIn('project_id', $visibleProjectIds))->count(),
            ],
        ]);
    }
}
