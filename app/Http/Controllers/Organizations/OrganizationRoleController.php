<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\DeleteOrganizationRole;
use App\Enums\OrganizationPermission;
use App\Events\MembersChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\OrganizationRoleRequest;
use App\Http\Resources\OrganizationMemberResource;
use App\Http\Resources\RoleCandidateResource;
use App\Http\Resources\RoleResource;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class OrganizationRoleController extends Controller
{
    public function index(Organization $currentOrganization): Response
    {
        return Inertia::render('roles/Index', [
            'roles' => MembersChanged::live(RoleResource::collection($currentOrganization->rolesWithMembers()->get()), $currentOrganization->id),
            'availablePermissions' => OrganizationPermission::options(),
        ]);
    }

    public function show(Organization $currentOrganization, Role $role): Response
    {
        $user = auth()->user();

        return Inertia::render('roles/Show', [
            'role' => MembersChanged::live(RoleResource::make($currentOrganization->rolesWithMembers()->findOrFail($role->id)), $currentOrganization->id),
            'members' => MembersChanged::live($currentOrganization->membershipsHolding($role)->map(fn ($membership) => OrganizationMemberResource::make($membership, $user)->resolve()), $currentOrganization->id),
            'availablePermissions' => OrganizationPermission::options(),
            'availableRoles' => MembersChanged::live($currentOrganization->rolesVisibleTo($user)->map(fn (Role $grantable) => ['value' => $grantable->id, 'label' => $grantable->name])->values(), $currentOrganization->id),
            'candidates' => MembersChanged::live(RoleCandidateResource::forRole($currentOrganization, $role, $user), $currentOrganization->id),
        ]);
    }

    public function store(OrganizationRoleRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->defineRole($request->validated('name'), $request->validated('permissions'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index', ['current_organization' => $currentOrganization->slug]);
    }

    public function update(OrganizationRoleRequest $request, Organization $currentOrganization, Role $role): RedirectResponse
    {
        $currentOrganization->redefineRole($role, $request->validated('name'), $request->validated('permissions'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index', ['current_organization' => $currentOrganization->slug]);
    }

    public function destroy(Organization $currentOrganization, Role $role, DeleteOrganizationRole $deleteOrganizationRole): RedirectResponse
    {
        $deleteOrganizationRole->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index', ['current_organization' => $currentOrganization->slug]);
    }
}
