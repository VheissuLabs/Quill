<?php

namespace App\Data;

readonly class AssistantMessage
{
    public function __construct(
        public string $id,
        public string $role,
        public string $content,
    ) {}
}
