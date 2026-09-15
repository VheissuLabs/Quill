<?php

namespace App\Data;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Collection;

readonly class CommentSummary
{
    /**
     * @param  array<int, array{id: int, name: string}>  $mentions
     * @param  array<int, self>  $replies
     */
    public function __construct(
        public int $id,
        public string $body,
        public ?string $authorName,
        public string $createdAt,
        public bool $isInternal,
        public bool $canDelete,
        public array $mentions,
        public array $replies,
    ) {
        //
    }

    public static function fromComment(Comment $comment, ?User $viewer): self
    {
        return new self(
            id: $comment->id,
            body: $comment->body,
            authorName: $comment->author?->name,
            createdAt: $comment->created_at->toISOString(),
            isInternal: $comment->is_internal,
            canDelete: $viewer?->can('delete', $comment) ?? false,
            mentions: $comment->mentions->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->all(),
            replies: $comment->relationLoaded('replies')
                ? $comment->replies->map(fn (Comment $reply) => self::fromComment($reply, $viewer))->all()
                : [],
        );
    }

    /**
     * @param  Collection<int, Comment>  $topLevel
     * @return Collection<int, self>
     */
    public static function threads(Collection $topLevel, ?User $viewer): Collection
    {
        return $topLevel->map(fn (Comment $comment) => self::fromComment($comment, $viewer));
    }
}
