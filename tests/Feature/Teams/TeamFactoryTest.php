<?php

use App\Models\Team;
use App\Models\User;

test('the factory lets the model generate the slug from the name', function () {
    $team = Team::factory()->create(['name' => 'Development Team']);

    expect($team->slug)->toBe('development-team');
});

test('a personal team slug matches the name it was created with', function () {
    $user = User::factory()->create(['name' => 'Test User']);

    $team = $user->personalTeam();

    expect($team->name)->toBe("Test User's Team");
    expect($team->slug)->toBe('test-users-team');
});

test('the personal state marks the team personal', function () {
    $team = Team::factory()->personal()->create();

    expect($team->is_personal)->toBeTrue();
});

test('the trashed state creates a soft deleted team', function () {
    $team = Team::factory()->trashed()->create();

    $this->assertSoftDeleted('teams', ['id' => $team->id]);
});

test('the withOwner state attaches an owner', function () {
    $team = Team::factory()->withOwner()->create();

    expect($team->owner)->not->toBeNull();
    expect($team->owner_id)->toBe($team->memberships->first()->user_id);
});

test('the withOwner state accepts a specific user', function () {
    $owner = User::factory()->create();

    $team = Team::factory()->withOwner($owner)->create();

    expect($owner->ownsTeam($team))->toBeTrue();
});

test('the withMembers state attaches the requested number of members', function () {
    $team = Team::factory()->withMembers(3)->create();

    expect($team->members)->toHaveCount(3);
    expect($team->memberships)->toHaveCount(3);
});

test('the withMembers state accepts a role and a specific user', function () {
    $admin = User::factory()->create();

    $team = Team::factory()->withMember($admin)->create();

    expect($admin->belongsToTeam($team))->toBeTrue();
});
