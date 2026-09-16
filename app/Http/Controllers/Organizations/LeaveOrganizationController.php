<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveOrganizationController extends Controller
{
    public function __invoke(Request $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();

        $organization->removeMember($user);

        if ($user->isCurrentOrganization($organization)) {
            $user->switchToFallbackOrganization($organization);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the organization ":name"', ['name' => $organization->name])]);

        return to_route('organizations.index');
    }
}
