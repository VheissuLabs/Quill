<?php

namespace App\Actions\Tickets;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailDirection;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReply;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ReplyToTicket
{
    /** @param  array<int, int>  $mentions */
    public function handle(Ticket $ticket, User $author, string $body, bool $internal = false, array $mentions = []): Comment
    {
        $comment = DB::transaction(function () use ($ticket, $author, $body, $internal, $mentions) {
            $comment = $ticket->comments()->create([
                'body' => $body,
                'is_internal' => $internal,
                'user_id' => $author->getKey(),
            ]);

            $comment->mentions()->sync($mentions);

            if (! $internal) {
                $this->recordOutboundEmail($ticket, $comment);
                $ticket->recordTeamReply();
            }

            return $comment;
        });

        if (! $internal) {
            $this->send($ticket, $comment);
        }

        return $comment;
    }

    public function send(Ticket $ticket, Comment $comment): void
    {
        $comment->emailMessage?->update(['delivery_status' => EmailDeliveryStatus::Queued]);

        Notification::route('mail', [$ticket->requester_email => $ticket->requester_name])
            ->notify(new TicketReply($comment));
    }

    private function recordOutboundEmail(Ticket $ticket, Comment $comment): void
    {
        $previous = $ticket->threadMessageIds();

        $comment->emailMessage()->create([
            'direction' => EmailDirection::Outbound,
            'delivery_status' => EmailDeliveryStatus::Queued,
            'from_email' => config('services.resend.support_address'),
            'from_name' => config('services.resend.support_name'),
            'to' => [$ticket->requester_email],
            'cc' => [],
            'message_id' => 'ticket-'.$ticket->getKey().'-'.$comment->getKey().'.'.Str::lower(Str::random(12)).'@'.Str::after(config('services.resend.support_address'), '@'),
            'in_reply_to' => $ticket->latestInboundMessage()?->message_id,
            'references' => $previous === [] ? null : collect($previous)->map(fn (string $id) => "<{$id}>")->implode(' '),
        ]);
    }
}
