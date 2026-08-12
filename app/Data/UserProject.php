<?php

namespace App\Data;

readonly class UserProject
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $ownerName,
        public ?string $ownerType,
    ) {}
}
