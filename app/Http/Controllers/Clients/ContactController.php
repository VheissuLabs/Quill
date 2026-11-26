<?php

namespace App\Http\Controllers\Clients;

use App\Events\ClientsChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientContactResource;
use App\Models\Organization;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Organization $currentOrganization): Response
    {
        return Inertia::render('contacts/Index', [
            'contacts' => ClientsChanged::live(ClientContactResource::collection($currentOrganization->clientContacts()->directory()->get()), $currentOrganization->id),
        ]);
    }
}
