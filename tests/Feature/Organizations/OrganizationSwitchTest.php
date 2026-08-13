<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('a user can switch to an organization they belong to', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, OrganizationRole::Member->value);

    $response = $this
        ->actingAs($user)
        ->post(route('organizations.switch', $organization));

    $response->assertRedirect();

    expect($user->fresh()->current_organization_id)->toBe($organization->id);
});

test('a client contact can switch into their organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, OrganizationRole::Client->value);

    $this
        ->actingAs($user)
        ->post(route('organizations.switch', $organization))
        ->assertRedirect();

    expect($user->fresh()->current_organization_id)->toBe($organization->id);
});

test('a user cannot switch to an organization they do not belong to', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('organizations.switch', $organization));

    $response->assertForbidden();

    expect($user->fresh()->current_organization_id)->not->toBe($organization->id);
});

test('guests cannot switch organizations', function () {
    $organization = Organization::factory()->create();

    $this
        ->post(route('organizations.switch', $organization))
        ->assertRedirect(route('login'));
});

test('the switch route resolves an organization by slug', function () {
    $organization = Organization::factory()->create(['name' => 'Notary Dash']);

    expect(route('organizations.switch', $organization))
        ->toContain('notary-dash');
});
