<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class CurrentOrganizationController extends Controller
{
    public function store(Organization $organization): RedirectResponse
    {
        auth()->user()->switchOrganization($organization);

        return redirect($organization->urlAfterSwitching(url()->previous()));
    }
}
