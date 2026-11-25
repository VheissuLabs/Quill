<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\CreateOrganizationInvitationRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrganizationInvitationController extends Controller
{
    public function store(CreateOrganizationInvitationRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->invite($request->validated('email'), $currentOrganization->roles()->findOrFail($request->integer('role_id')), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('organizations.edit', ['current_organization' => $currentOrganization->slug]);
    }

    public function destroy(Organization $currentOrganization, OrganizationInvitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('organizations.edit', ['current_organization' => $currentOrganization->slug]);
    }
}
