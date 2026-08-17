<?php

namespace Database\Factories;

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
            $owner ??= User::factory()->create();

            $organization->update(['owner_id' => $owner->id]);

            $this->addMember($organization, $owner, 'owner');
        });
    }

    public function withMembers(int $count = 3, string $role = 'member'): static
    {
        return $this->afterCreating(function (Organization $organization) use ($count, $role) {
            User::factory()
                ->count($count)
                ->create()
                ->each(fn (User $user) => $this->addMember($organization, $user, $role));
        });
    }

    public function withClientContact(?Client $client = null, ?User $contact = null): static
    {
        return $this->afterCreating(function (Organization $organization) use ($client, $contact) {
            $client ??= Client::factory()->heldBy($organization)->create();

            $this->addMember(
                $organization,
                $contact ?? User::factory()->create(),
                'client',
                $client,
            );
        });
    }

    protected function addMember(Organization $organization, User $user, string $role, ?Client $client = null): void
    {
        $organization->members()->attach($user, [
            'client_id' => $client?->id,
        ]);

        $user->assignOrganizationRole($organization, $role);
    }
}
