<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationMemberRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class OrganizationMemberController extends Controller
{
    /**
     * Update the specified organization member's role.
     */
    public function update(UpdateOrganizationMemberRequest $request, Organization $organization, User $user): RedirectResponse
    {
        $membership = $organization->memberships()->where('user_id', $user->id)->firstOrFail();
        $role = Role::findOrFail($request->integer('role_id'));

        Gate::authorize('update', [$membership, $role]);

        $organization->changeMemberRole($user, $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return back();
    }

    /**
     * Remove the specified organization member.
     */
    public function destroy(Organization $organization, User $user): RedirectResponse
    {
        $membership = $organization->memberships()->where('user_id', $user->id)->firstOrFail();

        Gate::authorize('delete', $membership);

        $organization->removeMember($user);

        if ($user->isCurrentOrganization($organization)) {
            $user->switchToFallbackOrganization($organization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }
}
