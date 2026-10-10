<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Tickets\ReplyToTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\RetryTicketReplyRequest;
use App\Models\Comment;
use App\Models\Organization;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketReplyDeliveryController extends Controller
{
    public function store(RetryTicketReplyRequest $request, Organization $currentOrganization, Ticket $ticket, Comment $comment, ReplyToTicket $reply): RedirectResponse
    {
        $reply->send($ticket, $comment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sending the reply again.')]);

        return back();
    }
}
