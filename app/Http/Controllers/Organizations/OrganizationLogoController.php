<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationLogoRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrganizationLogoController extends Controller
{
    /**
     * Replace the organization's logo, shown on its public portal instead of the app logo.
     */
    public function store(UpdateOrganizationLogoRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('update', $currentOrganization);

        $currentOrganization->replaceLogo($request->file('logo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo updated.')]);

        return to_route('organizations.edit', ['current_organization' => $currentOrganization->slug]);
    }

    /**
     * Remove the logo and fall back to the app logo on the portal.
     */
    public function destroy(Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('update', $currentOrganization);

        $currentOrganization->replaceLogo(null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logo removed.')]);

        return to_route('organizations.edit', ['current_organization' => $currentOrganization->slug]);
    }
}
