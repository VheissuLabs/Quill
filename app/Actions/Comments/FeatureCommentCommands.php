<?php

namespace App\Actions\Comments;

use App\Actions\Issues\CreateIssue;
use App\Enums\FeatureStatus;
use App\Enums\VoteDirection;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class FeatureCommentCommands extends CommentCommands
{
    public function __construct(
        private CreateIssue $createIssue
    ) {}

    /** @return array<int, array{name: string, usage: string, description: string}> */
    public function availableTo(?User $user, Model $commentable): array
    {
        if (! $user || ! $commentable instanceof Feature || ! $user->can('comment', $commentable)) {
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
        if (! $commentable instanceof Feature) {
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

    private function voteFor(Feature $feature, User $user): string
    {
        $this->authorize('vote', $feature, __('You can\'t vote on this request.'));

        $feature->castVote($user, VoteDirection::Up);

        return __('Voted for this request.');
    }

    private function voteAgainst(Feature $feature, User $user): string
    {
        $this->authorize('vote', $feature, __('You can\'t vote on this request.'));

        $feature->castVote($user, VoteDirection::Down);

        return __('Voted against this request.');
    }

    private function withdrawVote(Feature $feature, User $user): string
    {
        $this->authorize('vote', $feature, __('You can\'t vote on this request.'));

        $feature->votes()->where('user_id', $user->id)->delete();

        return __('Vote withdrawn.');
    }

    /** @return array{0: FeatureStatus, 1: string} */
    private function parseStatus(Feature $feature, string $argument): array
    {
        $this->authorize('updateStatus', $feature, __('You can\'t change the status.'));

        $names = collect(FeatureStatus::cases())
            ->flatMap(fn (FeatureStatus $status) => [
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
            'statuses' => collect(FeatureStatus::cases())->map(fn (FeatureStatus $status) => $status->label())->implode(', '),
        ])]);
    }

    private function status(Feature $feature, FeatureStatus $status): string
    {
        $feature->update(['status' => $status]);

        return __('Status set to :status.', ['status' => $status->label()]);
    }

    private function convert(Feature $feature, User $user): string
    {
        $this->authorize('convert', $feature, __('You can\'t convert this request.'));

        if ($feature->issue()->exists()) {
            throw ValidationException::withMessages(['body' => __('This request is already tracked as an issue.')]);
        }

        $issue = $this->createIssue->handle($feature->project, [
            'title' => $feature->title,
            'description' => $feature->description,
            'created_by' => $user->getKey(),
        ], $feature);

        return __('Added to the backlog as :reference.', ['reference' => $issue->reference()]);
    }

    private function internal(Feature $feature): bool
    {
        $this->authorize('commentInternally', $feature, __('Only organization members can post internal notes.'));

        return true;
    }
}
