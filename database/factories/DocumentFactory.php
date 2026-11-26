<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'documentable_type' => (new Project)->getMorphClass(),
            'documentable_id' => Project::factory(),
            'organization_id' => fn (array $attributes) => Project::query()->whereKey($attributes['documentable_id'])->value('organization_id'),
            'name' => fake()->word().'.pdf',
            'path' => 'documents/'.fake()->uuid().'.pdf',
            'content_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 500000),
        ];
    }
}
