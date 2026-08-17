<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Issue> */
class IssueFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => ucfirst(Str::rtrim(fake()->sentence(4), '.')),
            'description' => fake()->paragraph(),
        ];
    }

    public function inProject(Project $project): static
    {
        return $this->state(function () use ($project) {
            $type = IssueType::where('organization_id', $project->organization_id)->first();

            return [
                'organization_id' => $project->organization_id,
                'project_id' => $project->id,
                'issue_type_id' => $type !== null
                    ? $type->id
                    : IssueType::factory()->create(['organization_id' => $project->organization_id])->id,
                'reported_by' => User::factory()->create()->id,
            ];
        });
    }
}
