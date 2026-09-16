<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ProjectResource;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Organization $currentOrganization): Response
    {
        return Inertia::render('clients/Index', [
            'clients' => ClientResource::collection($currentOrganization->clients()->withCount('projects')->orderBy('name')->get()),
        ]);
    }

    public function store(ClientRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->clients()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return to_route('clients.index');
    }

    public function edit(Organization $currentOrganization, Client $client): Response
    {
        return Inertia::render('clients/Edit', [
            'client' => ClientResource::make($client),
            'projects' => ProjectResource::collection($client->projects()->orderBy('name')->get()),
        ]);
    }

    public function update(ClientRequest $request, Organization $currentOrganization, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return to_route('clients.edit', ['client' => $client->slug]);
    }

    public function destroy(Organization $currentOrganization, Client $client): RedirectResponse
    {
        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('clients.index');
    }
}
