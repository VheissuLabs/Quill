<?php

namespace App\Http\Resources;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public function __construct(Comment $resource, private ?User $viewer)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'authorName' => $this->author?->name,
            'createdAt' => $this->created_at->toISOString(),
            'isInternal' => $this->is_internal,
            'canDelete' => $this->viewer?->can('delete', $this->resource) ?? false,
            'mentions' => $this->mentions->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->all(),
            'replies' => $this->relationLoaded('replies')
                ? $this->replies->map(fn (Comment $reply) => self::make($reply, $this->viewer)->resolve())->all()
                : [],
        ];
    }
}
