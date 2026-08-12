<?php

namespace App\Data;

readonly class ActivityEntry
{
    public function __construct(
        public string $id,
        public string $summary,
        public ?string $causerName,
        public string $happenedAt,
        public string $happenedAtDiff,
    ) {}
}
