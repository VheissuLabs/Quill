<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\DeleteOrganizationRole;
use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\OrganizationRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class OrganizationRoleController extends Controller
{
    public function index(Organization $currentOrganization): Response
    {
        return Inertia::render('roles/Index', [
            'roles' => RoleResource::collection($currentOrganization->rolesWithMembers()->get()),
            'availablePermissions' => OrganizationPermission::options(),
        ]);
    }

    public function show(Organization $currentOrganization, Role $role): Response
    {
        $user = auth()->user();

        $grantableRoles = $currentOrganization->isOwner($user)
            ? $currentOrganization->roles()->orderByRaw("CASE WHEN name = 'Owner' THEN 0 ELSE 1 END")->orderBy('name')->get()
            : $currentOrganization->assignableRoles();

        return Inertia::render('roles/Show', [
            'role' => RoleResource::make($currentOrganization->rolesWithMembers()->findOrFail($role->id)),
            'availablePermissions' => OrganizationPermission::options(),
            'availableRoles' => $grantableRoles->map(fn (Role $grantable) => ['value' => $grantable->id, 'label' => $grantable->name])->values(),
            'candidates' => $currentOrganization->memberships()
                ->with(['user.organizationRoles' => fn ($query) => $query->wherePivot('organization_id', $currentOrganization->id)])
                ->get()
                ->map(fn ($membership) => $membership->user)
                ->filter(fn (User $member) => $member->organizationRole($currentOrganization)?->id !== $role->id)
                ->sortBy('name')
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->organizationRole($currentOrganization)?->name,
                ])
                ->values(),
        ]);
    }

    public function store(OrganizationRoleRequest $request, Organization $currentOrganization): RedirectResponse
    {
        DB::transaction(function () use ($request, $currentOrganization) {
            $currentOrganization->roles()
                ->create(['name' => $request->validated('name'), 'guard_name' => 'web'])
                ->syncPermissions($request->validated('permissions'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index', ['current_organization' => $currentOrganization->slug]);
    }

    public function update(OrganizationRoleRequest $request, Organization $currentOrganization, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role) {
            $role->update(['name' => $request->validated('name')]);
            $role->syncPermissions($request->validated('permissions'));
        });

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
