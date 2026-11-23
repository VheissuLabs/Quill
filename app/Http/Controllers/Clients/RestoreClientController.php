<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RestoreClientController extends Controller
{
    public function __invoke(Organization $currentOrganization, Client $client): RedirectResponse
    {
        $client->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client restored.')]);

        return to_route('clients.show', ['client' => $client]);
    }
}
