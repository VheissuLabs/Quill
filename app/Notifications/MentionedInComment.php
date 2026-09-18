<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Stored for the top bar and pushed over Reverb so it appears immediately. */
class MentionedInComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __(':name mentioned you on ":title"', [
                'name' => $this->comment->author->name ?? __('Someone'),
                'title' => $this->comment->subjectTitle(),
            ]),
            'body' => Str::limit($this->comment->body_text, 160),
            'url' => $this->comment->url(),
        ];
    }
}
