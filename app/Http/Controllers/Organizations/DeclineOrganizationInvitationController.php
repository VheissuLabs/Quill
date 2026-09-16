<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\RespondToOrganizationInvitationRequest;
use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DeclineOrganizationInvitationController extends Controller
{
    public function __invoke(RespondToOrganizationInvitationRequest $request, OrganizationInvitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return back();
    }
}
