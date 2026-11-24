<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateModuleOrderRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class OrganizationModuleOrderController extends Controller
{
    public function update(UpdateModuleOrderRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->update(['module_order' => $request->validated('order')]);

        return back();
    }
}
