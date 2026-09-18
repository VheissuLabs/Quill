<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackStatus;
use App\Enums\OrganizationCapability;
use App\Http\Resources\DashboardProjectResource;
use App\Http\Resources\PendingInvitationResource;
use App\Http\Resources\RecentFeedbackResource;
use App\Models\Feedback;
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
        $recentFeedback = collect();
        $visibleProjectIds = collect();

        if ($currentOrganization->canUse(OrganizationCapability::Boards)) {
            $visibleProjectIds = $currentOrganization->projects()->visibleTo($user)->pluck('projects.id');

            $projects = DashboardProjectResource::collection($currentOrganization->projects()
                ->visibleTo($user)
                ->with('client')
                ->withCount(['feedback', 'feedback as open_features_count' => fn ($query) => $query->where('status', FeedbackStatus::Open)])
                ->orderBy('name')
                ->get())->resolve();

            $recentFeedback = RecentFeedbackResource::collection(Feedback::whereIn('project_id', $visibleProjectIds)
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
            'recentFeedback' => $recentFeedback,
            'stats' => [
                'projectsCount' => count($projects),
                'openFeedbackCount' => collect($projects)->sum('openFeedbackCount'),
                'votesCount' => Vote::where('direction', 1)->whereHas('feedback', fn ($query) => $query->whereIn('project_id', $visibleProjectIds))->count(),
            ],
        ]);
    }
}
