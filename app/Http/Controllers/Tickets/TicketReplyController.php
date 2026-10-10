<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Comments\NotifyAboutComment;
use App\Actions\Tickets\ReplyToTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketReplyRequest;
use App\Models\Comment;
use App\Models\Organization;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketReplyController extends Controller
{
    public function store(StoreTicketReplyRequest $request, Organization $currentOrganization, Ticket $ticket, ReplyToTicket $reply, NotifyAboutComment $notify): RedirectResponse
    {
        $comment = $reply->handle(
            $ticket,
            $request->user(),
            $request->validated('body'),
            $request->boolean('internal'),
            $request->validated('mentions', []),
        );

        $notify->handle($comment, $request->user(), null);

        Inertia::flash('toast', ['type' => 'success', 'message' => $comment->is_internal
            ? __('Note added.')
            : __('Reply sent to :email.', ['email' => $ticket->requester_email])]);

        return back();
    }

    public function destroy(Organization $currentOrganization, Ticket $ticket, Comment $comment): RedirectResponse
    {
        $comment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note deleted.')]);

        return back();
    }
}
