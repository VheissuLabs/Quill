<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

test('the test user belongs to several teams beyond their personal one', function () {
    $this->seed();

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->teams()->where('is_personal', false)->count())->toBe(3);
    expect($user->personalTeam())->not->toBeNull();
});

test('the test user holds a different role in each seeded team', function () {
    $this->seed();

    $user = User::where('email', 'test@example.com')->firstOrFail();

    $roles = $user->teams()
        ->where('is_personal', false)
        ->get()
        ->map(fn (Team $team) => $user->teamRole($team))
        ->sortBy(fn (TeamRole $role) => $role->level())
        ->values()
        ->all();

    expect($roles)->toBe([TeamRole::Member, TeamRole::Admin, TeamRole::Owner]);
});

test('every seeded team has members besides the test user', function () {
    $this->seed();

    $user = User::where('email', 'test@example.com')->firstOrFail();

    $teams = Team::where('is_personal', false)->get();

    expect($teams)->toHaveCount(3);

    $teams->each(function (Team $team) use ($user) {
        expect($team->members->count())->toBeGreaterThan(1);
        expect($team->members->contains($user))->toBeTrue();
    });
});

test('every seeded team has an owner', function () {
    $this->seed();

    $teams = Team::where('is_personal', false)->get();

    expect($teams)->toHaveCount(3);

    $teams->each(fn (Team $team) => expect($team->owner())->not->toBeNull());
});

test('seeded team slugs are generated from their names', function () {
    $this->seed();

    expect(Team::where('is_personal', false)->pluck('slug')->sort()->values()->all())
        ->toBe(['design', 'development', 'quality-assurance']);
});
