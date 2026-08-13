<?php

namespace App\Http\Controllers;

use App\Concerns\SummarizesActivity;
use App\Data\ActivityEntry;
use App\Enums\OrganizationPermission;
use App\Models\Activity;
use App\Models\OrganizationInvitation;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use SummarizesActivity;

    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $pendingOrganizationInvitations = OrganizationInvitation::query()
            ->with(['inviter', 'organization', 'client'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (OrganizationInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'organizationName' => $invitation->organization->name,
                'clientName' => $invitation->client?->name,
                'roleLabel' => $invitation->role->label(),
            ]);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'pendingOrganizationInvitations' => $pendingOrganizationInvitations,
            'activity' => $this->activityFor($request->user()),
        ]);
    }

    /** @return LengthAwarePaginator<int, ActivityEntry>|null */
    protected function activityFor(User $user): ?LengthAwarePaginator
    {
        $organization = $user->currentOrganization;

        if ($organization === null || ! $user->hasOrganizationPermission($organization, OrganizationPermission::ViewActivity)) {
            return null;
        }

        return Activity::query()
            ->forOrganization($organization)
            ->with(['causer', 'subject'])
            ->latest()
            ->latest('id')
            ->paginate(15, pageName: 'activity')
            ->withQueryString()
            ->through(fn (Activity $activity) => $this->toActivityEntry($activity));
    }
}
