<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Feature;
use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use RuntimeException;

/** Stored for the top bar and pushed over Reverb so it appears immediately. */
class MentionedInComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment)
    {
        //
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        $commentable = $this->comment->commentable;

        return match (true) {
            $commentable instanceof Issue => $this->entry($commentable->title, route('issues.show', [
                'current_organization' => $commentable->project->organization,
                'issue' => $commentable,
            ], absolute: false)),
            $commentable instanceof Feature => $this->entry($commentable->title, route('portal.features.show', [
                'organization' => $commentable->project->organization,
                'project' => $commentable->project,
                'feature' => $commentable,
            ], absolute: false)),
            default => throw new RuntimeException('Mention on an unsupported subject.'),
        };
    }

    /** @return array{title: string, body: string, url: string} */
    private function entry(string $title, string $url): array
    {
        return [
            'title' => __(':name mentioned you on ":title"', [
                'name' => $this->comment->author->name ?? __('Someone'),
                'title' => $title,
            ]),
            'body' => Str::limit($this->comment->body, 160),
            'url' => $url,
        ];
    }
}
