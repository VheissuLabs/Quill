<?php

namespace App\Http\Controllers\Clients;

use App\Enums\AddressType;
use App\Events\ClientsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientContactLookupRequest;
use App\Http\Requests\Clients\ClientContactRequest;
use App\Http\Resources\ClientContactResource;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class ClientContactController extends Controller
{
    public function index(ClientContactLookupRequest $request, Organization $currentOrganization): AnonymousResourceCollection
    {
        return ClientContactResource::collection(
            mb_strlen($request->term()) < 2
                ? collect()
                : $currentOrganization->clientContacts()->matching($request->term())->get()
        );
    }

    public function show(Organization $currentOrganization, Client $client, ClientContact $contact): Response
    {
        return Inertia::render('clients/contacts/Show', [
            'client' => ['id' => $client->id, 'name' => $client->name, 'slug' => $client->slug],
            'contact' => ClientsChanged::live(ClientContactResource::make($contact->load('addresses'))->resolve(), $currentOrganization->id),
            'addressTypes' => AddressType::options(),
        ]);
    }

    public function store(ClientContactRequest $request, Organization $currentOrganization, Client $client): RedirectResponse
    {
        $client->contacts()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact added.')]);

        return back();
    }

    public function update(ClientContactRequest $request, Organization $currentOrganization, Client $client, ClientContact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        return back();
    }

    public function destroy(Organization $currentOrganization, Client $client, ClientContact $contact): RedirectResponse
    {
        $contact->delete();

        return to_route('clients.show', ['current_organization' => $currentOrganization, 'client' => $client]);
    }
}
