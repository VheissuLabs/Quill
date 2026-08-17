<?php

namespace App\Data;

readonly class UserTeam
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public bool $isPersonal,
        public bool $isOwner,
        public ?string $parentName = null,
        public ?string $parentType = null,
        public ?bool $isCurrent = null,
    ) {}
}
