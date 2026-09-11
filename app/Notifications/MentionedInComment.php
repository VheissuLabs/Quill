<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Stored for the top bar and pushed over Reverb so it appears immediately.
 */
class MentionedInComment extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Comment $comment)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification, used for both storage and broadcast.
     *
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $feature = $this->comment->feature;
        $project = $feature->project;

        return [
            'title' => __(':name mentioned you on ":title"', [
                'name' => $this->comment->author->name ?? __('Someone'),
                'title' => $feature->title,
            ]),
            'body' => Str::limit($this->comment->body, 160),
            'url' => route('portal.features.show', [$project->organization, $project, $feature], absolute: false),
        ];
    }
}
