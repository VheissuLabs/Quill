<?php

namespace App\Http\Controllers\Addresses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Addresses\AddressRequest;
use App\Models\Address;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class ClientAddressController extends Controller
{
    public function store(AddressRequest $request, Organization $currentOrganization, Client $client): RedirectResponse
    {
        $client->addresses()->create($request->validated());

        return back();
    }

    public function update(AddressRequest $request, Organization $currentOrganization, Client $client, Address $address): RedirectResponse
    {
        $address->update($request->validated());

        return back();
    }

    public function destroy(Organization $currentOrganization, Client $client, Address $address): RedirectResponse
    {
        $address->delete();

        return back();
    }
}
