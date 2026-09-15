<?php

namespace App\Data;

readonly class UserOrganization
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?string $role,
        public bool $isOwner = false,
        public bool $canUpdate = false,
        public bool $canManageRoles = false,
        /**
         * @var array<string, array{enabled: bool, capabilities: array<string, bool>}>
         */
        public array $modules = [],
        public ?bool $isCurrent = null,
    ) {
        //
    }
}
