<?php

namespace App\Ai\Contracts;

use Laravel\Ai\Contracts\Tool;

interface AssistantTool extends Tool
{
    public function name(): string;

    public function capability(): string;
}
