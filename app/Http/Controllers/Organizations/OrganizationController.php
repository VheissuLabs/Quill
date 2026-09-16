<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DeleteOrganizationRequest;
use App\Http\Requests\Organizations\OrganizationRequest;
use App\Http\Resources\UserOrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('organizations/Index', [
            'organizations' => $user->memberOrganizations(includeCurrent: true)->map(fn (Organization $organization) => UserOrganizationResource::make($organization, $user)->resolve()),
        ]);
    }

    public function store(OrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $organization = $createOrganization->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return to_route('organizations.edit', ['current_organization' => $organization->slug]);
    }

    public function edit(Request $request, Organization $currentOrganization): Response
    {
        $user = $request->user();

        return Inertia::render('organization/Settings', [
            'organization' => [
                'id' => $currentOrganization->id,
                'name' => $currentOrganization->name,
                'slug' => $currentOrganization->slug,
                'logoUrl' => $currentOrganization->logoUrl(),
                'isOwner' => $currentOrganization->isOwner($user),
                'modules' => $currentOrganization->moduleSettings(),
            ],
            'members' => $currentOrganization->memberships()
                ->with(['user.organizationRoles' => fn ($query) => $query->wherePivot('organization_id', $currentOrganization->id)])
                ->get()
                ->each(fn (OrganizationMembership $membership) => $membership->setRelation('organization', $currentOrganization))
                ->map(function (OrganizationMembership $membership) use ($currentOrganization, $user) {
                    $member = $membership->user;
                    $role = $member->organizationRole($currentOrganization);

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'avatar' => $member->avatar ?? null,
                        'role' => $role?->name,
                        'isOwner' => (bool) $role?->is_owner,
                        'canUpdate' => $user->can('update', $membership),
                        'canRemove' => $user->can('delete', $membership),
                    ];
                }),
            'invitations' => $currentOrganization->invitations()
                ->whereNull('accepted_at')
                ->with('role')
                ->get()
                ->map(fn ($invitation) => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->name,
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $user->toOrganizationPermissions($currentOrganization),
            'availableRoles' => $currentOrganization->assignableRoles()->map(fn (Role $role) => [
                'value' => $role->id,
                'label' => $role->name,
            ]),
            'memberRoles' => $currentOrganization->roles()
                ->when(! $currentOrganization->isOwner($user), fn ($query) => $query->where('is_owner', false))
                ->orderByDesc('is_owner')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'value' => $role->id,
                    'label' => $role->name,
                ]),
        ]);
    }

    public function update(OrganizationRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization = DB::transaction(function () use ($request, $currentOrganization) {
            $currentOrganization = Organization::whereKey($currentOrganization->id)->lockForUpdate()->firstOrFail();

            $currentOrganization->update(['name' => $request->validated('name')]);

            return $currentOrganization;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization updated.')]);

        return to_route('organizations.edit', ['current_organization' => $currentOrganization->slug]);
    }

    public function destroy(DeleteOrganizationRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $currentOrganization) {
            User::where('current_organization_id', $currentOrganization->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchToFallbackOrganization($currentOrganization));

            $currentOrganization->invitations()->delete();
            $currentOrganization->members->each(fn (User $member) => $currentOrganization->removeMember($member));
            $currentOrganization->delete();
        });

        if ($user->isCurrentOrganization($currentOrganization)) {
            $user->switchToFallbackOrganization($currentOrganization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization deleted.')]);

        return to_route('organizations.index');
    }
}
