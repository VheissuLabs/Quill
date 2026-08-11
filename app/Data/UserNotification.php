<?php

namespace App\Data;

readonly class UserNotification
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $organizationName,
        public string $createdAtDiff,
        public bool $isRead,
    ) {}
}
