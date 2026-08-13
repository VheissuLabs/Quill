<?php

namespace App\Ai\Tools\Concerns;

use Illuminate\Support\Collection;

trait MatchesNames
{
    /** The model rephrases, and "Acme Title Co." is the same company as "Acme Title Co". */
    protected function comparableName(string $name): string
    {
        $stripped = preg_replace('/[^\p{L}\p{N}\s]/u', '', mb_strtolower(trim($name)));

        return trim(preg_replace('/\s+/', ' ', $stripped ?? $name) ?? $name);
    }

    /**
     * `$noun` is stripped from both sides because the model relays the user's
     * phrasing: "the Development team" for a team named "Development".
     *
     * @param Collection<int, string> $names
     * @return Collection<int, string>
     */
    protected function matchingNames(Collection $names, string $wanted, string $noun): Collection
    {
        $target = $this->withoutNoun($wanted, $noun);

        $exact = $names->filter(fn (string $name) => $this->withoutNoun($name, $noun) === $target);

        if ($exact->isNotEmpty()) {
            return $exact->values();
        }

        return $names
            ->filter(fn (string $name) => str_contains($this->withoutNoun($name, $noun), $target))
            ->values();
    }

    protected function withoutNoun(string $name, string $noun): string
    {
        $name = $this->comparableName($name);
        $name = preg_replace('/\b'.preg_quote($noun, '/').'s?\b/', '', $name) ?? $name;
        $name = preg_replace('/^the\s+/', '', trim($name)) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }
}
