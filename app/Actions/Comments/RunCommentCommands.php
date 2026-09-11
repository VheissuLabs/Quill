<?php

namespace App\Actions\Comments;

use App\Actions\Issues\CreateIssue;
use App\Enums\FeatureStatus;
use App\Enums\VoteDirection;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Executes slash commands written at the start of comment lines, such as "/for" or
 * "/status planned", and returns the comment text with the commands removed. Text
 * after a command on the same line is kept as part of the comment.
 */
class RunCommentCommands
{
    public function __construct(private CreateIssue $createIssue)
    {
        //
    }

    /**
     * @return array{body: string, internal: bool, messages: array<int, string>}
     */
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

            if ($command === 'status') {
                [$status, $argument] = $this->parseStatus($feature, $argument);
                $messages[] = $this->status($feature, $status);
            } else {
                match ($command) {
                    'for', 'like', 'vote', 'upvote' => $messages[] = $this->voteFor($feature, $user),
                    'against', 'dislike', 'downvote' => $messages[] = $this->voteAgainst($feature, $user),
                    'withdraw', 'unlike', 'unvote' => $messages[] = $this->withdrawVote($feature, $user),
                    'convert' => $messages[] = $this->convert($feature, $user),
                    'internal' => $internal = $this->internal($feature, $user),
                    default => throw ValidationException::withMessages(['body' => __('Unknown command /:command.', ['command' => $command])]),
                };
            }

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

    /**
     * The commands the user may run on this feature, for the comment box picker.
     *
     * @return array<int, array{name: string, usage: string, description: string}>
     */
    public static function availableTo(?User $user, Feature $feature): array
    {
        if (! $user || ! $user->can('comment', $feature)) {
            return [];
        }

        $commands = [];

        if ($user->can('vote', $feature)) {
            $commands[] = ['name' => 'for', 'usage' => '/for', 'description' => 'Vote for this request'];
            $commands[] = ['name' => 'against', 'usage' => '/against', 'description' => 'Vote against this request'];
            $commands[] = ['name' => 'withdraw', 'usage' => '/withdraw', 'description' => 'Withdraw your vote'];
        }

        if ($user->can('commentInternally', $feature)) {
            $commands[] = ['name' => 'internal', 'usage' => '/internal', 'description' => 'Post as an internal note'];
        }

        if ($user->can('updateStatus', $feature)) {
            $commands[] = ['name' => 'status', 'usage' => '/status <status>', 'description' => 'Change the status'];
        }

        if ($user->can('convert', $feature)) {
            $commands[] = ['name' => 'convert', 'usage' => '/convert', 'description' => 'Convert into a backlog issue'];
        }

        return $commands;
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

    /**
     * Split the text after "/status" into the status it names and whatever follows it,
     * matching the longest name first so "in progress" wins over "in".
     *
     * @return array{0: FeatureStatus, 1: string}
     */
    private function parseStatus(Feature $feature, string $argument): array
    {
        $this->authorize('updateStatus', $feature, __('You can\'t change the status.'));

        $names = collect(FeatureStatus::cases())
            ->flatMap(fn (FeatureStatus $status) => [
                $status->value => $status,
                strtolower($status->label()) => $status,
                str_replace('_', ' ', $status->value) => $status,
            ])
            ->sortByDesc(fn (FeatureStatus $status, string $name) => strlen($name));

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

    private function internal(Feature $feature, User $user): bool
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
