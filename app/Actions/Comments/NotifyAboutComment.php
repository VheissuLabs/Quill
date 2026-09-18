<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;
use App\Notifications\MentionedInComment;
use App\Notifications\RepliedInThread;
use Illuminate\Database\Eloquent\Collection;

class NotifyAboutComment
{
    public function handle(Comment $comment, User $author, ?Comment $parent): void
    {
        $mentioned = $comment->mentions->reject(fn (User $user) => $user->is($author));

        $mentioned->each(fn (User $user) => $user->notify(new MentionedInComment($comment)));

        if (! $parent) {
            return;
        }

        $this->participants($comment, $parent)
            ->reject(fn (User $user) => $user->is($author) || $mentioned->contains(
                fn (User $mention) => $mention->is($user)
            ))
            ->each(fn (User $user) => $user->notify(new RepliedInThread($comment)));
    }

    /** @return Collection<int, User> */
    private function participants(Comment $comment, Comment $parent): Collection
    {
        $root = $parent->parent_id ?? $parent->getKey();

        $participants = User::query()
            ->whereIn('id', Comment::query()
                ->where(fn ($query) => $query->whereKey($root)->orWhere('parent_id', $root))
                ->whereKeyNot($comment->getKey())
                ->whereNotNull('user_id')
                ->select('user_id'))
            ->get();

        if (! $comment->is_internal) {
            return $participants;
        }

        $organization = $comment->organization();

        return $participants->filter(fn (User $user) => $user->belongsToOrganization($organization));
    }
}
