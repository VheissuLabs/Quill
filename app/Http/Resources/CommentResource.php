<?php

namespace App\Http\Resources;

use App\Models\Comment;
use App\Models\EmailAttachment;
use App\Models\User;
use App\Support\Window;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public const WINDOW_STEP = 15;

    public function __construct(Comment $resource, private ?User $viewer)
    {
        parent::__construct($resource);
    }

    /**
     * @param  MorphMany<Comment, covariant Model>  $comments
     * @return array{data: array<int, mixed>, hasMore: bool}
     */
    public static function window(MorphMany $comments, ?User $user, bool $isMember, bool $newestFirst = false): array
    {
        return Window::fromRequest(request(), self::WINDOW_STEP)->of(
            $comments->threadsVisibleTo($isMember, $newestFirst),
            fn (Comment $comment) => self::make($comment, $user)->resolve(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'authorName' => $this->author->name ?? $this->emailMessage->from_name ?? $this->emailMessage?->from_email,
            'createdAt' => $this->created_at->toISOString(),
            'isInternal' => $this->is_internal,
            'canDelete' => $this->viewer?->can('delete', $this->resource) ?? false,
            'email' => $this->emailMessage ? [
                'direction' => $this->emailMessage->direction->value,
                'deliveryStatus' => $this->emailMessage->delivery_status->value,
                'from' => $this->emailMessage->from_email,
                'to' => $this->emailMessage->to,
                'attachments' => $this->emailMessage->attachments->map(fn (EmailAttachment $attachment) => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'size' => $attachment->size,
                ])->all(),
            ] : null,
            'mentions' => $this->mentions->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->all(),
            'replies' => $this->relationLoaded('replies')
                ? $this->replies->map(fn (Comment $reply) => self::make($reply, $this->viewer)->resolve())->all()
                : [],
        ];
    }
}
