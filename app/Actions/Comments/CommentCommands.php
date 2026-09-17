<?php

namespace App\Actions\Comments;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

abstract class CommentCommands
{
    /** @return array{body: string, internal: bool, messages: array<int, string>} */
    public function handle(Model $commentable, User $user, string $body): array
    {
        $lines = collect(preg_split('/\R/', $body) ?: [])
            ->map(fn (string $line) => $this->runLine($commentable, $user, $line));

        return [
            'body' => trim($lines->pluck('text')->reject(fn (?string $text) => $text === null)->implode("\n")),
            'internal' => $lines->pluck('internal')->contains(true),
            'messages' => $lines->pluck('message')->reject(fn (?string $message) => $message === null)->values()->all(),
        ];
    }

    /** @return array<int, array{name: string, usage: string, description: string}> */
    abstract public function availableTo(?User $user, Model $commentable): array;

    /** @return array{text: ?string, message: ?string, internal: bool} */
    abstract protected function run(Model $commentable, User $user, string $command, string $argument): array;

    /** @return array{text: ?string, message: ?string, internal: bool} */
    protected function runLine(Model $commentable, User $user, string $line): array
    {
        if (! preg_match('/^\/(\w+)(?:\s+(.*))?$/', trim($line), $matches)) {
            return ['text' => $line, 'message' => null, 'internal' => false];
        }

        return $this->run($commentable, $user, strtolower($matches[1]), trim($matches[2] ?? ''));
    }

    protected function authorize(string $ability, Model $subject, string $message): void
    {
        if (! Gate::allows($ability, $subject)) {
            throw ValidationException::withMessages(['body' => $message]);
        }
    }

    protected function unknown(string $command): never
    {
        throw ValidationException::withMessages(['body' => __('Unknown command /:command.', ['command' => $command])]);
    }
}
