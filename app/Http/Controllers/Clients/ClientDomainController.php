<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientDomainRequest;
use App\Models\Client;
use App\Models\ClientDomain;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ClientDomainController extends Controller
{
    public function store(StoreClientDomainRequest $request, Organization $currentOrganization, Client $client): RedirectResponse
    {
        $client->domains()->create(['domain' => $request->validated('domain')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mail from :domain now goes to :client.', ['domain' => $request->validated('domain'), 'client' => $client->name])]);

        return back();
    }

    public function destroy(Organization $currentOrganization, Client $client, ClientDomain $domain): RedirectResponse
    {
        $domain->delete();

        return back();
    }
}
