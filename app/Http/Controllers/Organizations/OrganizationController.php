<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Events\MembersChanged;
use App\Events\ModulesChanged;
use App\Events\OrganizationChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DeleteOrganizationRequest;
use App\Http\Requests\Organizations\OrganizationRequest;
use App\Http\Resources\OrganizationInvitationResource;
use App\Http\Resources\OrganizationMemberResource;
use App\Http\Resources\OrganizationSettingsResource;
use App\Http\Resources\RoleOptionResource;
use App\Http\Resources\UserOrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('organizations/Index', [
            'organizations' => OrganizationChanged::liveAcross($organizations = $user->memberOrganizations(includeCurrent: true)
                ->map(fn (Organization $organization) => UserOrganizationResource::make($organization, $user)->resolve()), $organizations->pluck('id'), MembersChanged::class),
        ]);
    }

    public function store(OrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $organization = $createOrganization->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return to_route('organizations.edit', ['current_organization' => $organization->slug]);
    }

    public function edit(Organization $currentOrganization): Response
    {
        $user = auth()->user();

        return Inertia::render('organization/Settings', [
            'organization' => OrganizationChanged::live(OrganizationSettingsResource::make($currentOrganization, $user), $currentOrganization->id, ModulesChanged::class),
            'members' => MembersChanged::live($currentOrganization->membershipsWithRoles()
                ->map(fn (OrganizationMembership $membership) => OrganizationMemberResource::make($membership, $user)->resolve()), $currentOrganization->id),
            'invitations' => MembersChanged::live(OrganizationInvitationResource::collection(
                $currentOrganization->invitations()->whereNull('accepted_at')->with('role')->get()
            ), $currentOrganization->id),
            'availableRoles' => MembersChanged::live(RoleOptionResource::collection($currentOrganization->assignableRoles()), $currentOrganization->id),
            'memberRoles' => MembersChanged::live(RoleOptionResource::collection($currentOrganization->rolesVisibleTo($user)), $currentOrganization->id),
        ]);
    }

    public function update(OrganizationRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization = $currentOrganization->renameTo($request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization updated.')]);

        return to_route('organizations.edit', ['current_organization' => $currentOrganization->slug]);
    }

    public function destroy(DeleteOrganizationRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $user = $request->user();

        $currentOrganization->deleteWith($user);

        if ($user->isCurrentOrganization($currentOrganization)) {
            $user->switchToFallbackOrganization($currentOrganization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization deleted.')]);

        return to_route('organizations.index');
    }
}
