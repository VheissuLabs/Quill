<?php

use App\Actions\Organizations\CreateOrganization;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('a new user gets no organization', function () {
    $user = User::factory()->create();

    expect($user->organizations()->count())->toBe(0);
    expect($user->current_organization_id)->toBeNull();
})->note('Creating a first organization is PR 3. Until then a fresh signup has nowhere to land.');

test('the create organization action makes the user its owner', function () {
    $user = User::factory()->create();

    $organization = app(CreateOrganization::class)->handle($user, 'NotaryDash');

    expect($organization->slug)->toBe('notarydash');
    expect($user->organizationRoleName($organization))->toBe(OrganizationRole::Owner->value);
});

test('creating an organization switches the user into it', function () {
    $user = User::factory()->create();

    $organization = app(CreateOrganization::class)->handle($user, 'NotaryDash');

    expect($user->fresh()->current_organization_id)->toBe($organization->id);
});

test('organizations have no notion of being personal', function () {
    expect(Organization::factory()->create()->getAttributes())
        ->not->toHaveKey('is_personal');

    expect(Schema::hasColumn('organizations', 'is_personal'))->toBeFalse();
});

test('registration still creates a personal team for now', function () {
    $user = User::factory()->create();

    expect($user->personalTeam())->not->toBeNull();
})->note('Temporary: personal teams are removed in PR 2c.');
