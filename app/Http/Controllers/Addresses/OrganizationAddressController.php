<?php

namespace App\Http\Controllers\Addresses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Addresses\AddressRequest;
use App\Models\Address;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class OrganizationAddressController extends Controller
{
    public function store(AddressRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->addresses()->create($request->validated());

        return back();
    }

    public function update(AddressRequest $request, Organization $currentOrganization, Address $address): RedirectResponse
    {
        $address->update($request->validated());

        return back();
    }

    public function destroy(Organization $currentOrganization, Address $address): RedirectResponse
    {
        $address->delete();

        return back();
    }
}
