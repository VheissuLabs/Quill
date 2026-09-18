<?php

namespace App\Actions\Comments;

use App\Models\User;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

abstract class CommentCommands
{
    /** @return array{body: string, internal: bool, messages: array<int, string>} */
    public function handle(Model $commentable, User $user, string $body): array
    {
        return str_contains($body, '<')
            ? $this->handleRichText($commentable, $user, $body)
            : $this->handlePlainText($commentable, $user, $body);
    }

    /** @return array{body: string, internal: bool, messages: array<int, string>} */
    protected function handlePlainText(Model $commentable, User $user, string $body): array
    {
        $lines = collect(preg_split('/\R/', $body) ?: [])
            ->map(fn (string $line) => $this->runLine($commentable, $user, $line));

        return [
            'body' => trim($lines->pluck('text')->reject(fn (?string $text) => $text === null)->implode("\n")),
            'internal' => $lines->pluck('internal')->contains(true),
            'messages' => $lines->pluck('message')->reject(fn (?string $message) => $message === null)->values()->all(),
        ];
    }

    /** @return array{body: string, internal: bool, messages: array<int, string>} */
    protected function handleRichText(Model $commentable, User $user, string $body): array
    {
        $blocks = collect($this->blocks($body))
            ->map(fn (string $block) => $this->runBlock($commentable, $user, $block));

        $remaining = $blocks->pluck('html')->implode('');

        return [
            'body' => RichText::toText($remaining) === '' ? '' : trim($remaining),
            'internal' => $blocks->pluck('internal')->contains(true),
            'messages' => $blocks->pluck('message')->reject(fn (?string $message) => $message === null)->values()->all(),
        ];
    }

    /** @return array<int, string> */
    protected function blocks(string $body): array
    {
        return preg_split(
            '#(<p\b[^>]*>.*?</p>)#is',
            $body,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        ) ?: [$body];
    }

    /** @return array{html: string, message: ?string, internal: bool} */
    protected function runBlock(Model $commentable, User $user, string $block): array
    {
        if (! str_starts_with($block, '<p')) {
            return ['html' => $block, 'message' => null, 'internal' => false];
        }

        $result = $this->runLine($commentable, $user, RichText::toText($block));

        return [
            'html' => $result['text'] === null ? '' : $block,
            'message' => $result['message'],
            'internal' => $result['internal'],
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
