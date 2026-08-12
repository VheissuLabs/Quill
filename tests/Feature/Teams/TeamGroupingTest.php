<?php

use App\Enums\OrganizationRole;
use App\Enums\TeamRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;

test('a team carries the name and kind of its parent', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $client = Client::factory()->for($organization)->create(['name' => 'Acme Title Co']);
    $user = User::factory()->create();

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, OrganizationRole::Owner->value);

    $orgTeam = Team::factory()->heldBy($organization)->withMember($user, TeamRole::Owner)->create(['name' => 'Delivery']);
    $clientTeam = Team::factory()->heldBy($client)->withMember($user, TeamRole::Owner)->create(['name' => 'Development']);

    expect($user->toUserTeam($orgTeam)->parentName)->toBe('NotaryDash');
    expect($user->toUserTeam($orgTeam)->parentType)->toBe('organization');

    expect($user->toUserTeam($clientTeam)->parentName)->toBe('Acme Title Co');
    expect($user->toUserTeam($clientTeam)->parentType)->toBe('client');
});

test('a personal team has no parent name', function () {
    $user = User::factory()->create();

    $userTeam = $user->toUserTeam($user->personalTeam());

    expect($userTeam->parentName)->toBeNull();
    expect($userTeam->parentType)->toBeNull();
});

test('the seeded team list carries the parent each team hangs off', function () {
    $this->seed();

    $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();
    $notaryDash = Organization::where('name', 'NotaryDash')->firstOrFail();

    $grouped = $user->toUserTeams(includeCurrent: true, organization: $notaryDash)
        ->groupBy('parentName')
        ->map(fn ($teams) => $teams->pluck('name')->sort()->values()->all())
        ->sortKeys()
        ->all();

    expect($grouped)->toBe([
        'Acme Title Co' => ['Design', 'Engineering'],
        'Harbor Escrow' => ['Quality Assurance'],
        'NotaryDash' => ['Delivery'],
    ]);
});

test('the shared team list includes the parent name', function () {
    $this->seed();

    $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

    $this
        ->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('teams', 4)
            ->where('teams.0.parentType', 'organization'),
        );
});

test('listing teams does not lazy load a parent per team', function () {
    $this->seed();

    $user = User::where('email', 'karl@vheissulabs.com')->firstOrFail();
    $notaryDash = Organization::where('name', 'NotaryDash')->firstOrFail();

    DB::enableQueryLog();

    $user->toUserTeams(includeCurrent: true, organization: $notaryDash);

    $queries = collect(DB::getQueryLog())
        ->filter(fn (array $query) => str_contains($query['query'], 'from `clients`'))
        ->count();

    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(1, 'clients should be eager loaded in one query');
});
