<?php

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

test('users get uuid version 7 keys', function () {
    $user = User::factory()->create();

    expect($user->id)->toBeString();
    expect(Str::isUuid($user->id))->toBeTrue();
    expect(Uuid::fromString($user->id)->getVersion())->toBe(7);
});

test('teams get uuid keys and still resolve by slug', function () {
    $team = Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);

    expect(Str::isUuid($team->id))->toBeTrue();
    expect($team->getRouteKeyName())->toBe('slug');
    expect($team->getRouteKey())->toBe('acme');
});

test('memberships get uuid keys and join correctly', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->update(['owner_id' => $user->id]);
    $team->members()->attach($user);

    $membership = $team->memberships->first();

    expect(Str::isUuid($membership->id))->toBeTrue();
    expect($membership->user_id)->toBe($user->id);
    expect($membership->team_id)->toBe($team->id);
    expect($user->ownsTeam($team))->toBeTrue();
});

test('invitations get uuid keys and still resolve by code', function () {
    $invitation = TeamInvitation::factory()->create();

    expect(Str::isUuid($invitation->id))->toBeTrue();
    expect($invitation->getRouteKeyName())->toBe('code');
});

test('a personal team is created and selected for every new user', function () {
    $user = User::factory()->create();

    expect($user->personalTeam())->not->toBeNull();
    expect($user->current_team_id)->toBe($user->personalTeam()->id);
    expect(Str::isUuid($user->current_team_id))->toBeTrue();
});
