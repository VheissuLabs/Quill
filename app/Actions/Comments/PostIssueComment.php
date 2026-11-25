<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PostIssueComment
{
    public function __construct(private IssueCommentCommands $commands, private NotifyAboutComment $notify) {}

    /**
     * @param  array<int, int>  $mentions
     * @return array{comment: Comment|null, messages: array<int, string>}
     */
    public function handle(Issue $issue, User $author, string $body, array $mentions = [], ?Comment $parent = null): array
    {
        $result = $this->commands->handle($issue, $author, $body);

        if ($result['body'] === '') {
            return ['comment' => null, 'messages' => $result['messages']];
        }

        $comment = DB::transaction(function () use ($issue, $author, $mentions, $parent, $result) {
            $comment = $issue->comments()->create([
                'body' => $result['body'],
                'is_internal' => false,
                'parent_id' => $parent->parent_id ?? $parent?->id,
                'user_id' => $author->id,
            ]);

            $comment->mentions()->sync($mentions);

            return $comment;
        });

        $this->notify->handle($comment, $author, $parent);

        return ['comment' => $comment, 'messages' => $result['messages']];
    }
}
