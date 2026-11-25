<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PostFeedbackComment
{
    public function __construct(
        private FeedbackCommentCommands $commands,
        private NotifyAboutComment $notify,
    ) {}

    /**
     * @param  array<int, int>  $mentions
     * @return array{comment: Comment|null, messages: array<int, string>}
     */
    public function handle(Feedback $feedback, User $author, string $body, bool $internal = false, ?int $parentId = null, array $mentions = []): array
    {
        $parent = $parentId !== null
            ? $feedback->comments()->whereKey($parentId)->firstOrFail()
            : null;

        $result = $this->commands->handle($feedback, $author, $body);

        $internal = $internal || $result['internal'] || ($parent->is_internal ?? false);

        if ($result['body'] === '') {
            return ['comment' => null, 'messages' => $result['messages']];
        }

        $comment = DB::transaction(function () use ($feedback, $author, $parent, $internal, $result, $mentions) {
            $comment = $feedback->comments()->create([
                'body' => $result['body'],
                'is_internal' => $internal,
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
