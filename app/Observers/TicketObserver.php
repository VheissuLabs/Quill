<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Ticket;

class TicketObserver
{
    public function creating(Ticket $ticket): void
    {
        $ticket->number ??= (Ticket::query()->where('organization_id', $ticket->organization_id)->max('number') ?? 0) + 1;
        $ticket->last_message_at ??= now();
    }

    public function deleting(Ticket $ticket): void
    {
        Comment::withoutEvents(
            fn () => $ticket->comments()->with('emailMessage')->lazy()->each(function (Comment $comment): void {
                $comment->emailMessage?->delete();
                $comment->delete();
            })
        );
    }
}
