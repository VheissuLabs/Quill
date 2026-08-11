<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('the trashed state creates a soft deleted organization', function () {
    $organization = Organization::factory()->trashed()->create();

    $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
});

test('the withOwner state attaches an owner', function () {
    $organization = Organization::factory()->withOwner()->create();

    expect($organization->owner())->not->toBeNull();
    expect($organization->memberships->first()->role)->toBe(OrganizationRole::Owner);
});

test('the withOwner state accepts a specific user', function () {
    $owner = User::factory()->create();

    $organization = Organization::factory()->withOwner($owner)->create();

    expect($owner->ownsOrganization($organization))->toBeTrue();
});

test('the withMembers state attaches the requested number of members', function () {
    $organization = Organization::factory()->withMembers(4)->create();

    expect($organization->members)->toHaveCount(4);
    expect($organization->memberships->pluck('role')->unique()->all())
        ->toBe([OrganizationRole::Member]);
});

test('the withMembers state accepts a role', function () {
    $organization = Organization::factory()->withMembers(2, OrganizationRole::Admin)->create();

    expect($organization->memberships->pluck('role')->unique()->all())
        ->toBe([OrganizationRole::Admin]);
});

test('the withClientContact state attaches a client', function () {
    $organization = Organization::factory()->withClientContact()->create();

    $contact = $organization->members->first();

    expect($contact->isClientContact($organization))->toBeTrue();
});

test('states compose', function () {
    $organization = Organization::factory()
        ->withOwner()
        ->withMembers(2)
        ->withClientContact()
        ->create();

    expect($organization->members)->toHaveCount(4);
    expect($organization->owner())->not->toBeNull();
});
