<?php

namespace Database\Factories;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization) {
            $organization->roles()->create(['name' => 'Admin', 'guard_name' => 'web'])->syncPermissions([
                OrganizationPermission::UpdateOrganization->value,
                OrganizationPermission::CreateInvitation->value,
                OrganizationPermission::CancelInvitation->value,
                OrganizationPermission::ManageTeams->value,
                OrganizationPermission::ManageClients->value,
                OrganizationPermission::ManageProjects->value,
                OrganizationPermission::ManageFeedback->value,
                OrganizationPermission::ManageIssues->value,
                OrganizationPermission::ManageSprints->value,
                OrganizationPermission::ViewActivity->value,
            ]);
            $organization->roles()->create(['name' => 'Member', 'guard_name' => 'web']);
        });
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
