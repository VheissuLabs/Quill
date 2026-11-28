<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\DestroyTicketsRequest;
use App\Models\Organization;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketDeletionController extends Controller
{
    public function destroy(DestroyTicketsRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $deleted = Ticket::deleteMany($currentOrganization, $request->ticketIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} :count ticket deleted.|[2,*] :count tickets deleted.', $deleted)]);

        return back();
    }
}
