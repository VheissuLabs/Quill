<?php

namespace App\Ai\Contracts;

use Laravel\Ai\Contracts\Tool;

/**
 * The SDK resolves a tool's name via `is_callable([$tool, 'name'])` rather than
 * through its contract, so the convention is undeclared. This states it.
 */
interface AssistantTool extends Tool
{
    public function name(): string;
}
