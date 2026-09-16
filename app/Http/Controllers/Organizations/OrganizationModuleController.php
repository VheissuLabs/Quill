<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationModulesRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrganizationModuleController extends Controller
{
    public function update(UpdateOrganizationModulesRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->updateModuleSettings($request->validated());

        if (! $request->boolean('teams_enabled')) {
            $currentOrganization->dissolveTeams();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product modules updated.')]);

        return to_route('organizations.edit', ['current_organization' => $currentOrganization->slug]);
    }
}
