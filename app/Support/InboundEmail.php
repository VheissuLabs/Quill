<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class InboundEmail
{
    private const QUOTE_MARKERS = [
        '/^On\b[^\n]*(\n[^\n]*)?\bwrote:\s*$/m',
        '/^-{2,}\s*Original Message\s*-{2,}/mi',
        '/^_{10,}\s*$/m',
        '/^From:\s.+\n(Sent|Date):\s.+$/mi',
        '/^--\s*$/m',
    ];

    /** @param  array<string, mixed>  $payload */
    public function __construct(private array $payload) {}

    public function resendId(): string
    {
        return (string) $this->payload['id'];
    }

    public function subject(): string
    {
        return Str::limit(trim((string) ($this->payload['subject'] ?? '')), 250, '') ?: __('(no subject)');
    }

    public function fromEmail(): string
    {
        return self::address((string) ($this->payload['from'] ?? ''));
    }

    public function fromName(): ?string
    {
        $header = (string) ($this->header('from') ?? $this->payload['from'] ?? '');

        return preg_match('/^\s*"?([^"<]+?)"?\s*</', $header, $matches)
            ? trim($matches[1])
            : null;
    }

    /** @return array<int, string> */
    public function to(): array
    {
        return self::addresses($this->payload['to'] ?? []);
    }

    /** @return array<int, string> */
    public function cc(): array
    {
        return self::addresses($this->payload['cc'] ?? []);
    }

    /** @return array<int, string> */
    public function recipients(): array
    {
        return [...$this->to(), ...$this->cc(), ...self::addresses($this->payload['received_for'] ?? [])];
    }

    public function messageId(): string
    {
        return (string) ($this->payload['message_id'] ?? $this->header('message-id') ?? $this->resendId());
    }

    public function inReplyTo(): ?string
    {
        return $this->header('in-reply-to');
    }

    public function references(): ?string
    {
        return $this->header('references');
    }

    /** @return array<int, string> */
    public function referencedMessageIds(): array
    {
        preg_match_all('/<[^>]+>/', trim(($this->inReplyTo() ?? '').' '.($this->references() ?? '')), $matches);

        return array_values(array_unique($matches[0]));
    }

    public function isAutomated(): bool
    {
        $autoSubmitted = strtolower($this->header('auto-submitted') ?? 'no');
        $precedence = strtolower($this->header('precedence') ?? '');
        $sender = Str::before($this->fromEmail(), '@');

        return $autoSubmitted !== 'no'
            || in_array($precedence, ['bulk', 'junk', 'list', 'auto_reply'], true)
            || $this->header('x-autoreply') !== null
            || $this->header('x-autorespond') !== null
            || in_array($sender, ['mailer-daemon', 'postmaster'], true);
    }

    public function html(): ?string
    {
        $html = $this->payload['html'] ?? null;

        return is_string($html) && $html !== '' ? $html : null;
    }

    public function text(): string
    {
        $text = $this->payload['text'] ?? null;

        return is_string($text) && trim($text) !== ''
            ? str_replace("\r\n", "\n", $text)
            : RichText::toText($this->html() ?? '');
    }

    public function visibleText(): string
    {
        $text = $this->text();

        $cut = collect(self::QUOTE_MARKERS)
            ->map(fn (string $pattern) => preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE) ? $matches[0][1] : null)
            ->filter(fn (?int $offset) => $offset !== null && $offset > 0)
            ->min();

        $visible = collect(explode("\n", $cut === null ? $text : substr($text, 0, $cut)))
            ->reject(fn (string $line) => str_starts_with(ltrim($line), '>'))
            ->implode("\n");

        return trim($visible) !== '' ? trim($visible) : trim($text);
    }

    public function visibleHtml(): string
    {
        return collect(preg_split("/\n\s*\n/", $this->visibleText()) ?: [])
            ->map(fn (string $paragraph) => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');
    }

    /** @return array<int, array{id: string, filename: string, content_type: string, size: int}> */
    public function attachments(): array
    {
        return collect(is_array($this->payload['attachments'] ?? null) ? $this->payload['attachments'] : [])
            ->filter(fn (mixed $attachment) => is_array($attachment) && isset($attachment['id']))
            ->map(fn (array $attachment) => [
                'id' => (string) $attachment['id'],
                'filename' => (string) ($attachment['filename'] ?? 'attachment'),
                'content_type' => (string) ($attachment['content_type'] ?? 'application/octet-stream'),
                'size' => (int) ($attachment['size'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public function header(string $name): ?string
    {
        $headers = is_array($this->payload['headers'] ?? null)
            ? array_change_key_case($this->payload['headers'])
            : [];

        $value = $headers[strtolower($name)] ?? null;

        return match (true) {
            is_array($value) => implode(' ', Arr::flatten($value)),
            is_string($value) => $value,
            default => null,
        };
    }

    public static function address(string $value): string
    {
        return strtolower(preg_match('/<([^>]+)>/', $value, $matches) ? trim($matches[1]) : trim($value));
    }

    /** @return array<int, string> */
    private static function addresses(mixed $values): array
    {
        return array_values(array_map(
            fn (string $value) => self::address($value),
            array_filter(is_array($values) ? $values : [$values], fn (mixed $value) => is_string($value) && $value !== '')
        ));
    }
}
