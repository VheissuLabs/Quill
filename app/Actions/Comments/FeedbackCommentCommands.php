<?php

namespace App\Actions\Comments;

use App\Actions\Issues\CreateIssue;
use App\Enums\FeedbackStatus;
use App\Enums\VoteDirection;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class FeedbackCommentCommands extends CommentCommands
{
    public function __construct(
        private CreateIssue $createIssue
    ) {}

    /** @return array<int, array{name: string, usage: string, description: string}> */
    public function availableTo(?User $user, Model $commentable): array
    {
        if (! $user || ! $commentable instanceof Feedback || ! $user->can('comment', $commentable)) {
            return [];
        }

        return collect([
            ['ability' => 'vote', 'name' => 'for', 'usage' => '/for', 'description' => 'Vote for this request'],
            ['ability' => 'vote', 'name' => 'against', 'usage' => '/against', 'description' => 'Vote against this request'],
            ['ability' => 'vote', 'name' => 'withdraw', 'usage' => '/withdraw', 'description' => 'Withdraw your vote'],
            ['ability' => 'commentInternally', 'name' => 'internal', 'usage' => '/internal', 'description' => 'Post as an internal note'],
            ['ability' => 'updateStatus', 'name' => 'status', 'usage' => '/status <status>', 'description' => 'Change the status'],
            ['ability' => 'convert', 'name' => 'convert', 'usage' => '/convert', 'description' => 'Convert into a backlog issue'],
        ])
            ->filter(fn (array $command) => $user->can($command['ability'], $commentable))
            ->map(fn (array $command) => [
                'name' => $command['name'],
                'usage' => $command['usage'],
                'description' => $command['description'],
            ])
            ->values()
            ->all();
    }

    /** @return array{text: ?string, message: ?string, internal: bool} */
    protected function run(Model $commentable, User $user, string $command, string $argument): array
    {
        if (! $commentable instanceof Feedback) {
            $this->unknown($command);
        }

        $status = null;

        if ($command === 'status') {
            [$status, $argument] = $this->parseStatus($commentable, $argument);
        }

        $outcome = match ($command) {
            'status' => ['message' => $this->status($commentable, $status)],
            'for', 'like', 'vote', 'upvote' => ['message' => $this->voteFor($commentable, $user)],
            'against', 'dislike', 'downvote' => ['message' => $this->voteAgainst($commentable, $user)],
            'withdraw', 'unlike', 'unvote' => ['message' => $this->withdrawVote($commentable, $user)],
            'convert' => ['message' => $this->convert($commentable, $user)],
            'internal' => ['internal' => $this->internal($commentable)],
            default => $this->unknown($command),
        };

        return [
            'text' => $argument !== '' ? $argument : null,
            'message' => $outcome['message'] ?? null,
            'internal' => $outcome['internal'] ?? false,
        ];
    }

    private function voteFor(Feedback $feedback, User $user): string
    {
        $this->authorize('vote', $feedback, __('You can\'t vote on this request.'));

        $feedback->castVote($user, VoteDirection::Up);

        return __('Voted for this request.');
    }

    private function voteAgainst(Feedback $feedback, User $user): string
    {
        $this->authorize('vote', $feedback, __('You can\'t vote on this request.'));

        $feedback->castVote($user, VoteDirection::Down);

        return __('Voted against this request.');
    }

    private function withdrawVote(Feedback $feedback, User $user): string
    {
        $this->authorize('vote', $feedback, __('You can\'t vote on this request.'));

        $feedback->votes()->where('user_id', $user->id)->delete();

        return __('Vote withdrawn.');
    }

    /** @return array{0: FeedbackStatus, 1: string} */
    private function parseStatus(Feedback $feedback, string $argument): array
    {
        $this->authorize('updateStatus', $feedback, __('You can\'t change the status.'));

        $names = collect(FeedbackStatus::cases())
            ->flatMap(fn (FeedbackStatus $status) => [
                $status->value => $status,
                strtolower($status->label()) => $status,
                str_replace('_', ' ', $status->value) => $status,
            ])
            ->sortKeysUsing(fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($names as $name => $status) {
            if (preg_match('/^'.preg_quote($name, '/').'(?=\s|$)(.*)$/i', $argument, $matches)) {
                return [$status, trim($matches[1])];
            }
        }

        throw ValidationException::withMessages(['body' => __('Unknown status ":status". Use one of: :statuses.', [
            'status' => $argument,
            'statuses' => collect(FeedbackStatus::cases())->map(fn (FeedbackStatus $status) => $status->label())->implode(', '),
        ])]);
    }

    private function status(Feedback $feedback, FeedbackStatus $status): string
    {
        $feedback->update(['status' => $status]);

        return __('Status set to :status.', ['status' => $status->label()]);
    }

    private function convert(Feedback $feedback, User $user): string
    {
        $this->authorize('convert', $feedback, __('You can\'t convert this request.'));

        if ($feedback->issue()->exists()) {
            throw ValidationException::withMessages(['body' => __('This request is already tracked as an issue.')]);
        }

        $issue = $this->createIssue->handle($feedback->project, [
            'title' => $feedback->title,
            'description' => $feedback->description,
            'created_by' => $user->getKey(),
        ], $feedback);

        return __('Added to the backlog as :reference.', ['reference' => $issue->reference()]);
    }

    private function internal(Feedback $feedback): bool
    {
        $this->authorize('commentInternally', $feedback, __('Only organization members can post internal notes.'));

        return true;
    }
}
