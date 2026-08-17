<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CurrentOrganizationController extends Controller
{
    public function update(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('view', $organization);

        $request->user()->switchOrganization($organization);

        return back();
    }
}
