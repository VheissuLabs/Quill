<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the current organization and the full list are shared with every page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, OrganizationRole::Owner->value);
    $user->switchOrganization($organization);

    $this
        ->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentOrganization.name', 'NotaryDash')
            ->where('currentOrganization.isCurrent', true)
            ->where('currentOrganization.role', OrganizationRole::Owner->value)
            ->has('organizations', 1),
        );
});

test('a guest page shares no organizations', function () {
    $this
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentOrganization', null)
            ->where('organizations', []),
        );
});
