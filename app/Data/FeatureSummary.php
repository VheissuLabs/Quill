<?php

namespace App\Data;

use App\Enums\VoteDirection;
use App\Models\Feature;

readonly class FeatureSummary
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $status,
        public string $statusLabel,
        public int $votesCount,
        public int $downvotesCount,
        public int $score,
        public int $commentsCount,
        public ?string $vote,
        public ?string $authorName,
        public string $createdAt,
    ) {
        //
    }

    /**
     * Build the summary from a feature loaded with its author, comment count and vote summary
     * (see Feature::withVoteSummary).
     */
    public static function fromFeature(Feature $feature): self
    {
        return new self(
            id: $feature->id,
            title: $feature->title,
            description: $feature->description,
            status: $feature->status->value,
            statusLabel: $feature->status->label(),
            votesCount: (int) $feature->votes_count,
            downvotesCount: (int) $feature->downvotes_count,
            score: (int) $feature->score,
            commentsCount: (int) $feature->comments_count,
            vote: $feature->user_vote === null ? null : VoteDirection::fromWeight((int) $feature->user_vote)->value,
            authorName: $feature->author?->name,
            createdAt: $feature->created_at->toISOString(),
        );
    }
}
