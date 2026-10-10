<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;

class TicketReadyToReply extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  Collection<int, Ticket>  $tickets */
    public function __construct(public Issue $issue, public Collection $tickets) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        $first = $this->tickets->firstOrFail();

        return [
            'title' => trans_choice(
                '{1} :reference is done. Let the customer on :ticket know.|[2,*] :reference is done. Let the customers on :count tickets know.',
                $this->tickets->count(),
                ['reference' => $this->issue->reference(), 'ticket' => $first->reference(), 'count' => $this->tickets->count()],
            ),
            'body' => $this->tickets->map(fn (Ticket $ticket) => "{$ticket->reference()} {$ticket->subject}")->implode(', '),
            'url' => $this->tickets->count() === 1
                ? route('tickets.show', ['current_organization' => $first->organization, 'ticket' => $first], absolute: false)
                : route('tickets.index', ['current_organization' => $first->organization, 'view' => 'ready'], absolute: false),
        ];
    }
}
