<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\RespondToOrganizationInvitationRequest;
use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrganizationInvitationAcceptanceController extends Controller
{
    public function store(
        RespondToOrganizationInvitationRequest $request,
        OrganizationInvitation $invitation,
        AcceptInvitation $acceptInvitation,
    ): RedirectResponse {
        $acceptInvitation->handle($request->user(), $invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

        return to_route('dashboard', ['current_team' => $request->user()->currentTeam?->slug]);
    }
}
