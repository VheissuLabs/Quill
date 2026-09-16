<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DeleteOrganizationMemberRequest;
use App\Http\Requests\Organizations\UpdateOrganizationMemberRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class OrganizationMemberController extends Controller
{
    public function update(UpdateOrganizationMemberRequest $request, Organization $currentOrganization, User $user): RedirectResponse
    {
        $membership = $currentOrganization->memberships()->where('user_id', $user->id)->firstOrFail();
        $role = Role::findOrFail($request->integer('role_id'));

        $currentOrganization->changeMemberRole($user, $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return back();
    }

    public function destroy(DeleteOrganizationMemberRequest $request, Organization $currentOrganization, User $user): RedirectResponse
    {
        $membership = $currentOrganization->memberships()->where('user_id', $user->id)->firstOrFail();

        $currentOrganization->removeMember($user);

        if ($user->isCurrentOrganization($currentOrganization)) {
            $user->switchToFallbackOrganization($currentOrganization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('organizations.edit', ['current_organization' => $currentOrganization->slug]);
    }
}
