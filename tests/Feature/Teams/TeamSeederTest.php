<?php

use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;

test('every seeded team has a parent, and both parent kinds appear', function () {
    $this->seed();

    $teams = Team::where('is_personal', false)->get();

    expect($teams)->toHaveCount(6);

    $teams->each(fn (Team $team) => expect($team->parent)->not->toBeNull());

    expect($teams->firstWhere('name', 'Delivery')->parent)
        ->toBeInstanceOf(Organization::class);

    expect($teams->firstWhere('name', 'Development')->parent)
        ->toBeInstanceOf(Client::class);
});

test('the team list is scoped to the organization the user is in', function () {
    $this->seed();

    $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

    $expected = [
        'NotaryDash' => ['Delivery', 'Design', 'Development', 'Quality Assurance'],
        '92 Labs' => ['Platform'],
        'VheissuLabs' => ['Audio Tools'],
    ];

    foreach ($expected as $organizationName => $teamNames) {
        $organization = Organization::where('name', $organizationName)->firstOrFail();

        $actual = $user->toUserTeams(includeCurrent: true, organization: $organization)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        expect($actual)->toBe($teamNames);
    }
});

test('the test user holds a different role across the seeded teams', function () {
    $this->seed();

    $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

    $roles = Team::where('is_personal', false)
        ->get()
        ->mapWithKeys(fn (Team $team) => [$team->name => $user->teamRole($team)]);

    expect($roles['Development'])->toBe(TeamRole::Owner);
    expect($roles['Design'])->toBe(TeamRole::Admin);
    expect($roles['Quality Assurance'])->toBe(TeamRole::Member);
});

test('every seeded team has an owner and members besides the test user', function () {
    $this->seed();

    $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

    $teams = Team::where('is_personal', false)->get();

    expect($teams)->toHaveCount(6);

    $teams->each(function (Team $team) use ($user) {
        expect($team->owner())->not->toBeNull();
        expect($team->members->count())->toBeGreaterThan(1);
        expect($team->members->contains($user))->toBeTrue();
    });
});

test('seeded team slugs are generated from their names', function () {
    $this->seed();

    expect(Team::where('is_personal', false)->pluck('slug')->sort()->values()->all())
        ->toBe(['audio-tools', 'delivery', 'design', 'development', 'platform', 'quality-assurance']);
});
