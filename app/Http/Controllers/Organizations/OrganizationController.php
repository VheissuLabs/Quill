<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DeleteOrganizationRequest;
use App\Http\Requests\Organizations\SaveOrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the user's organizations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('organizations/Index', [
            'organizations' => $user->toUserOrganizations(includeCurrent: true),
        ]);
    }

    /**
     * Store a newly created organization.
     */
    public function store(SaveOrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $organization = $createOrganization->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }

    /**
     * Show the organization edit page.
     */
    public function edit(Request $request, Organization $organization): Response
    {
        $user = $request->user();

        return Inertia::render('organizations/Edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'logoUrl' => $organization->logoUrl(),
            ],
            'members' => $organization->memberships()
                ->with(['user.organizationRoles' => fn ($query) => $query->wherePivot('organization_id', $organization->id)])
                ->get()
                ->each(fn (OrganizationMembership $membership) => $membership->setRelation('organization', $organization))
                ->map(function (OrganizationMembership $membership) use ($organization, $user) {
                    $member = $membership->user;
                    $role = $member->organizationRole($organization);

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
            'invitations' => $organization->invitations()
                ->whereNull('accepted_at')
                ->with('role')
                ->get()
                ->map(fn ($invitation) => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->name,
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $user->toOrganizationPermissions($organization),
            'availableRoles' => $organization->assignableRoles()->map(fn (Role $role) => [
                'value' => $role->id,
                'label' => $role->name,
            ]),
            'memberRoles' => $organization->roles()
                ->when(! $organization->isOwner($user), fn ($query) => $query->where('is_owner', false))
                ->orderByDesc('is_owner')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'value' => $role->id,
                    'label' => $role->name,
                ]),
        ]);
    }

    /**
     * Update the specified organization.
     */
    public function update(SaveOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('update', $organization);

        $organization = DB::transaction(function () use ($request, $organization) {
            $organization = Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $organization->update(['name' => $request->validated('name')]);

            return $organization;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization updated.')]);

        return to_route('organizations.edit', ['organization' => $organization->slug]);
    }

    /**
     * Switch the user's current organization.
     */
    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($request->user()->belongsToOrganization($organization), 403);

        $request->user()->switchOrganization($organization);

        return redirect($this->urlAfterSwitching($organization));
    }

    /**
     * Where to land after switching: the same page inside the new organization when the page is
     * organization-wide (the organization is the only thing in its URL), the dashboard when the
     * page was about one record that does not exist over there, and right back where the user was
     * for pages outside any organization.
     */
    protected function urlAfterSwitching(Organization $organization): string
    {
        $previous = url()->previous();
        $dashboard = route('dashboard', ['current_organization' => $organization]);

        try {
            $route = Route::getRoutes()->match(Request::create($previous));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return $dashboard;
        }

        $parameters = $route->parameterNames();

        if (! in_array('current_organization', $parameters, true)) {
            return $previous;
        }

        if (count($parameters) > 1 || ! $route->getName()) {
            return $dashboard;
        }

        $query = parse_url($previous, PHP_URL_QUERY);

        return route($route->getName(), ['current_organization' => $organization]).($query ? "?{$query}" : '');
    }

    /**
     * Leave the specified organization.
     */
    public function leave(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('leave', $organization);

        $user = $request->user();

        $organization->removeMember($user);

        if ($user->isCurrentOrganization($organization)) {
            $user->switchToFallbackOrganization($organization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the organization ":name"', ['name' => $organization->name])]);

        return to_route('organizations.index');
    }

    /**
     * Delete the specified organization.
     */
    public function destroy(DeleteOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $organization) {
            User::where('current_organization_id', $organization->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchToFallbackOrganization($organization));

            $organization->invitations()->delete();
            $organization->members->each(fn (User $member) => $organization->removeMember($member));
            $organization->delete();
        });

        if ($user->isCurrentOrganization($organization)) {
            $user->switchToFallbackOrganization($organization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization deleted.')]);

        return to_route('organizations.index');
    }
}
