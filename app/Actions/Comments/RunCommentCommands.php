<?php

namespace App\Actions\Comments;

use App\Actions\Issues\CreateIssue;
use App\Enums\FeatureStatus;
use App\Enums\VoteDirection;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RunCommentCommands
{
    public function __construct(
        private CreateIssue $createIssue
    ) {}

    /** @return array{body: string, internal: bool, messages: array<int, string>} */
    public function handle(Feature $feature, User $user, string $body): array
    {
        $remaining = [];
        $messages = [];
        $internal = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (! preg_match('/^\/(\w+)(?:\s+(.*))?$/', trim($line), $matches)) {
                $remaining[] = $line;

                continue;
            }

            $command = strtolower($matches[1]);
            $argument = trim($matches[2] ?? '');
            $status = null;

            if ($command === 'status') {
                [$status, $argument] = $this->parseStatus($feature, $argument);
            }

            match ($command) {
                'status' => $messages[] = $this->status($feature, $status),
                'for', 'like', 'vote', 'upvote' => $messages[] = $this->voteFor($feature, $user),
                'against', 'dislike', 'downvote' => $messages[] = $this->voteAgainst($feature, $user),
                'withdraw', 'unlike', 'unvote' => $messages[] = $this->withdrawVote($feature, $user),
                'convert' => $messages[] = $this->convert($feature, $user),
                'internal' => $internal = $this->internal($feature),
                default => throw ValidationException::withMessages(['body' => __('Unknown command /:command.', ['command' => $command])]),
            };

            if ($argument !== '') {
                $remaining[] = $argument;
            }
        }

        return [
            'body' => trim(implode("\n", $remaining)),
            'internal' => $internal,
            'messages' => $messages,
        ];
    }

    /** @return array<int, array{name: string, usage: string, description: string}> */
    public static function availableTo(?User $user, Feature $feature): array
    {
        if (! $user || ! $user->can('comment', $feature)) {
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
            ->filter(fn (array $command) => $user->can($command['ability'], $feature))
            ->map(fn (array $command) => Arr::except($command, 'ability'))
            ->values()
            ->all();
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

        $issue = $this->createIssue->handle($feature->project, $user, [
            'title' => $feature->title,
            'description' => $feature->description,
        ], $feature);

        return __('Added to the backlog as :reference.', ['reference' => $issue->reference()]);
    }

    private function internal(Feature $feature): bool
    {
        $this->authorize('commentInternally', $feature, __('Only organization members can post internal notes.'));

        return true;
    }

    private function authorize(string $ability, Feature $feature, string $message): void
    {
        if (! Gate::allows($ability, $feature)) {
            throw ValidationException::withMessages(['body' => $message]);
        }
    }
}
