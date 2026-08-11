<?php

use App\Actions\Organizations\CreateOrganization;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('a new user gets a personal organization they own', function () {
    $user = User::factory()->create(['name' => 'Karl Murray']);

    $organization = $user->personalOrganization();

    expect($organization)->not->toBeNull();
    expect($organization->name)->toBe("Karl Murray's Organization");
    expect($organization->is_personal)->toBeTrue();
    expect($user->ownsOrganization($organization))->toBeTrue();
});

test('the personal organization is the users current organization', function () {
    $user = User::factory()->create();

    expect($user->current_organization_id)->toBe($user->personalOrganization()->id);
    expect($user->currentOrganization->id)->toBe($user->personalOrganization()->id);
});

test('a personal organization slug comes from its name', function () {
    $user = User::factory()->create(['name' => 'Karl Murray']);

    expect($user->personalOrganization()->slug)->toBe('karl-murrays-organization');
});

test('the personal factory state marks an organization personal', function () {
    expect(Organization::factory()->personal()->create()->is_personal)->toBeTrue();
    expect(Organization::factory()->create()->is_personal)->toBeFalse();
});

test('the create organization action makes the user its owner', function () {
    $user = User::factory()->create();

    $organization = app(CreateOrganization::class)->handle($user, 'NotaryDash');

    expect($organization->is_personal)->toBeFalse();
    expect($organization->slug)->toBe('notarydash');
    expect($user->organizationRole($organization))->toBe(OrganizationRole::Owner);
});

test('creating an organization switches the user into it', function () {
    $user = User::factory()->create();

    $organization = app(CreateOrganization::class)->handle($user, 'NotaryDash');

    expect($user->fresh()->current_organization_id)->toBe($organization->id);
});

test('registration still creates a personal team for now', function () {
    $user = User::factory()->create();

    expect($user->personalTeam())->not->toBeNull();
})->note('Temporary: personal teams are removed in PR 2c, once organizations are the workspace.');
