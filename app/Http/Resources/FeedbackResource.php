<?php

namespace App\Http\Resources;

use App\Enums\VoteDirection;
use App\Events\FeedbackChanged;
use App\Events\FeedbackCommented;
use App\Events\FeedbackVoted;
use App\Events\IssuesChanged;
use App\Models\Feedback;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\LiveProp;

/** @mixin Feedback */
class FeedbackResource extends JsonResource
{
    private bool $withIssue = false;

    public static function liveForIssue(Issue $issue): LiveProp
    {
        return FeedbackChanged::alsoRefresh(
            IssuesChanged::live($issue->feedback ? self::make($issue->feedback)->resolve() : null, $issue->project->organization_id),
            array_filter([$issue->feedback?->project_id]),
            FeedbackVoted::class,
            FeedbackCommented::class,
        );
    }

    public function withIssue(bool $withIssue = true): static
    {
        $this->withIssue = $withIssue;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'typeLabel' => $this->type->shortLabel(),
            'voteLabel' => $this->type->voteLabel(),
            'acceptsOpposingVotes' => $this->type->acceptsOpposingVotes(),
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
