<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Team> */
class TeamFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'is_personal' => false,
        ];
    }

    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_personal' => true,
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }

    /**
     * Nest the team under an organization or a client. Personal teams have no
     * parent at all, which is why this is opt-in rather than a default.
     */
    public function heldBy(Organization|Client $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $parent instanceof Organization
                ? $parent->id
                : $parent->organization_id,
            'parent_type' => $parent::class,
            'parent_id' => $parent->id,
        ]);
    }

    public function withOwner(?User $owner = null): static
    {
        return $this->withMember($owner ?? User::factory()->create(), TeamRole::Owner);
    }

    public function withMember(User $member, TeamRole $role = TeamRole::Member): static
    {
        return $this->afterCreating(function (Team $team) use ($member, $role) {
            $team->members()->attach($member, ['role' => $role->value]);
        });
    }

    public function withMembers(int $count = 3, TeamRole $role = TeamRole::Member): static
    {
        return $this->afterCreating(function (Team $team) use ($count, $role) {
            User::factory()
                ->count($count)
                ->create()
                ->each(fn (User $user) => $team->members()->attach($user, [
                    'role' => $role->value,
                ]));
        });
    }
}
