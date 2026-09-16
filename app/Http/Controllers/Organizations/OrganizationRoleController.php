<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\OrganizationRoleRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class OrganizationRoleController extends Controller
{
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Role::class, $currentOrganization]);

        return Inertia::render('roles/Index', [
            'roles' => $this->roles($currentOrganization),
            'availablePermissions' => OrganizationPermission::options(),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function show(Request $request, Organization $currentOrganization, Role $role): Response
    {
        $this->ensureRoleBelongsTo($currentOrganization, $role);

        Gate::authorize('viewAny', [Role::class, $currentOrganization]);

        $user = $request->user();

        $grantableRoles = $currentOrganization->isOwner($user)
            ? $currentOrganization->roles()->orderByDesc('is_owner')->orderBy('name')->get()
            : $currentOrganization->assignableRoles();

        return Inertia::render('roles/Show', [
            'role' => $this->roles($currentOrganization)->firstWhere('id', $role->id),
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
            'permissions' => $user->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function store(OrganizationRoleRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [Role::class, $currentOrganization]);

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
        $this->ensureRoleBelongsTo($currentOrganization, $role);

        Gate::authorize('update', $role);

        DB::transaction(function () use ($request, $role) {
            $role->update(['name' => $request->validated('name')]);
            $role->syncPermissions($request->validated('permissions'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.index', ['current_organization' => $currentOrganization->slug]);
    }

    public function destroy(Organization $currentOrganization, Role $role): RedirectResponse
    {
        $this->ensureRoleBelongsTo($currentOrganization, $role);

        Gate::authorize('delete', $role);

        abort_if(
            DB::table('model_has_roles')->where('role_id', $role->id)->exists(),
            409,
            __('Move its members to another role before deleting this one.'),
        );

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index', ['current_organization' => $currentOrganization->slug]);
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function roles(Organization $organization): Collection
    {
        $holders = DB::table('model_has_roles')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.organization_id', $organization->id)
            ->where('model_has_roles.model_type', User::class)
            ->orderBy('users.name')
            ->get(['model_has_roles.role_id', 'users.id', 'users.name', 'users.email'])
            ->groupBy('role_id');

        return $organization->roles()
            ->with('permissions')
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'isOwner' => (bool) $role->is_owner,
                'permissions' => $role->permissions->pluck('name')->values(),
                'membersCount' => $holders->get($role->id, collect())->count(),
                'members' => $holders->get($role->id, collect())->map(fn ($holder) => ['id' => $holder->id, 'name' => $holder->name, 'email' => $holder->email])->values(),
            ]);
    }

    protected function ensureRoleBelongsTo(Organization $organization, Role $role): void
    {
        abort_unless((int) $role->getAttribute(config('permission.column_names.team_foreign_key')) === $organization->id, 404);
    }
}
