<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TicketReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment, public bool $isNewTicket) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        $sender = $this->comment->author->name
            ?? $this->comment->emailMessage->from_name
            ?? $this->comment->emailMessage->from_email
            ?? __('A customer');

        return [
            'title' => $this->isNewTicket
                ? __('New ticket from :sender: ":title"', ['sender' => $sender, 'title' => $this->comment->subjectTitle()])
                : __(':sender replied on ":title"', ['sender' => $sender, 'title' => $this->comment->subjectTitle()]),
            'body' => Str::limit($this->comment->body_text, 160),
            'url' => $this->comment->url(),
        ];
    }
}
