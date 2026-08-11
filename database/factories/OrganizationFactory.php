<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
        ];
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }

    public function withOwner(?User $owner = null): static
    {
        return $this->afterCreating(function (Organization $organization) use ($owner) {
            $organization->members()->attach(
                $owner ?? User::factory()->create(),
                ['role' => OrganizationRole::Owner->value],
            );
        });
    }

    public function withMembers(int $count = 3, OrganizationRole $role = OrganizationRole::Member): static
    {
        return $this->afterCreating(function (Organization $organization) use ($count, $role) {
            User::factory()
                ->count($count)
                ->create()
                ->each(fn (User $user) => $organization->members()->attach($user, [
                    'role' => $role->value,
                ]));
        });
    }

    public function withClientContact(?Client $client = null, ?User $contact = null): static
    {
        return $this->afterCreating(function (Organization $organization) use ($client, $contact) {
            $client ??= Client::factory()->heldBy($organization)->create();

            $organization->members()->attach(
                $contact ?? User::factory()->create(),
                [
                    'role' => OrganizationRole::Client->value,
                    'client_id' => $client->id,
                ],
            );
        });
    }
}
