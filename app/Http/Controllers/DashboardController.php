<?php

namespace App\Http\Controllers;

use App\Events\FeedbackChanged;
use App\Events\FeedbackCommented;
use App\Events\FeedbackVoted;
use App\Events\ModulesChanged;
use App\Events\ProjectsChanged;
use App\Http\Resources\DashboardResource;
use App\Http\Resources\PendingInvitationResource;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Organization $currentOrganization): Response
    {
        $user = auth()->user();
        $dashboard = DashboardResource::make($currentOrganization, $user);
        $summary = $dashboard->resolve();
        $visibleProjectIds = $dashboard->visibleProjectIds();

        $organizationId = $currentOrganization->getKey();
        $feedbackEvents = [FeedbackVoted::class, FeedbackCommented::class];

        return Inertia::render('Dashboard', [
            'pendingInvitations' => PendingInvitationResource::collection(OrganizationInvitation::pendingFor($user)->get()),
            'projects' => FeedbackChanged::alsoRefresh(ProjectsChanged::live($summary['projects'], $organizationId, ModulesChanged::class), $visibleProjectIds),
            'recentFeedback' => FeedbackChanged::alsoRefresh(ProjectsChanged::live($summary['recentFeedback'], $organizationId, ModulesChanged::class), $visibleProjectIds, ...$feedbackEvents),
            'stats' => FeedbackChanged::alsoRefresh(ProjectsChanged::live($summary['stats'], $organizationId, ModulesChanged::class), $visibleProjectIds, ...$feedbackEvents),
        ]);
    }
}
