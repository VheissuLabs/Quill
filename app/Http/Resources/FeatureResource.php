<?php

namespace App\Http\Resources;

use App\Enums\VoteDirection;
use App\Models\Feature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Feature */
class FeatureResource extends JsonResource
{
    public function __construct(Feature $resource, private bool $withIssue = false)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'acceptsVotes' => ! $this->status->isSettled(),
            'votesCount' => (int) $this->votes_count,
            'downvotesCount' => (int) $this->downvotes_count,
            'score' => (int) $this->score,
            'commentsCount' => (int) $this->comments_count,
            'vote' => $this->user_vote === null ? null : VoteDirection::fromWeight((int) $this->user_vote)->value,
            'authorName' => $this->author?->name,
            'createdAt' => $this->created_at->toISOString(),
            'issue' => $this->withIssue && $this->issue ? [
                'id' => $this->issue->id,
                'reference' => $this->issue->reference(),
                'status' => $this->issue->status->value,
                'statusLabel' => $this->issue->status->label(),
            ] : null,
        ];
    }
}
