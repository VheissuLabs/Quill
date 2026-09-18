<?php

namespace Database\Factories;

use App\Enums\IssueLinkType;
use App\Models\Issue;
use App\Models\IssueLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IssueLink> */
class IssueLinkFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'related_issue_id' => Issue::factory(),
            'type' => IssueLinkType::Relates,
        ];
    }
}
