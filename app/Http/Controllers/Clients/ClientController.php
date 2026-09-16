<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request, Organization $currentOrganization): Response
    {
        Gate::authorize('viewAny', [Client::class, $currentOrganization]);

        return Inertia::render('clients/Index', [
            'clients' => $currentOrganization->clients()
                ->withCount('projects')
                ->orderBy('name')
                ->get()
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'slug' => $client->slug,
                    'projectsCount' => $client->projects_count,
                ]),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function store(ClientRequest $request, Organization $currentOrganization): RedirectResponse
    {
        Gate::authorize('create', [Client::class, $currentOrganization]);

        $currentOrganization->clients()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return to_route('clients.index');
    }

    public function edit(Request $request, Organization $currentOrganization, Client $client): Response
    {
        Gate::authorize('view', $client);

        return Inertia::render('clients/Edit', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
            ],
            'projects' => $client->projects()
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'visibility' => $project->visibility->value,
                ]),
            'permissions' => $request->user()->toOrganizationPermissions($currentOrganization),
        ]);
    }

    public function update(ClientRequest $request, Organization $currentOrganization, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return to_route('clients.edit', ['client' => $client->slug]);
    }

    public function destroy(Organization $currentOrganization, Client $client): RedirectResponse
    {
        Gate::authorize('delete', $client);

        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('clients.index');
    }
}
