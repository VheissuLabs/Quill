<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;

test('a client is held by its organization by default', function () {
    $organization = Organization::factory()->create();

    $client = Client::factory()->for($organization)->create();

    expect($client->parent)->toBeInstanceOf(Organization::class);
    expect($client->parent->id)->toBe($organization->id);
    expect($organization->childClients->pluck('id')->all())->toBe([$client->id]);
});

test('a team can be held by an organization', function () {
    $organization = Organization::factory()->create();

    $team = Team::factory()->heldBy($organization)->create();

    expect($team->parent)->toBeInstanceOf(Organization::class);
    expect($team->organization_id)->toBe($organization->id);
    expect($organization->childTeams->pluck('id')->all())->toBe([$team->id]);
});

test('a team can be held by a client', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create();

    $team = Team::factory()->heldBy($client)->create();

    expect($team->parent)->toBeInstanceOf(Client::class);
    expect($team->parent->id)->toBe($client->id);
    expect($team->organization_id)->toBe($organization->id);
    expect($client->teams->pluck('id')->all())->toBe([$team->id]);
});

test('a client can be held by a team', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->heldBy($organization)->create();

    $client = Client::factory()->heldBy($team)->create();

    expect($client->parent)->toBeInstanceOf(Team::class);
    expect($client->parent->id)->toBe($team->id);
    expect($client->organization_id)->toBe($organization->id);
    expect($team->clients->pluck('id')->all())->toBe([$client->id]);
});

test('the full nesting the spec describes is expressible', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);

    $delivery = Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);
    $acme = Client::factory()->heldBy($delivery)->create(['name' => 'Acme']);
    $acmeDev = Team::factory()->heldBy($acme)->create(['name' => 'Acme Dev']);

    expect($acmeDev->parent->parent->parent->id)->toBe($organization->id);

    expect($organization->teams()->pluck('name')->sort()->values()->all())
        ->toBe(['Acme Dev', 'Delivery']);

    expect($organization->childTeams()->pluck('name')->all())->toBe(['Delivery']);
});

test('a parent in another organization is rejected', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    $team = Team::factory()->heldBy($other)->create();

    expect(fn () => Client::factory()->create([
        'organization_id' => $organization->id,
        'parent_type' => Team::class,
        'parent_id' => $team->id,
    ]))->toThrow(RuntimeException::class, 'another organization');
});

test('a team cannot be held by its own descendant', function () {
    $organization = Organization::factory()->create();

    $team = Team::factory()->heldBy($organization)->create();
    $client = Client::factory()->heldBy($team)->create();

    expect(fn () => $team->update([
        'parent_type' => Client::class,
        'parent_id' => $client->id,
    ]))->toThrow(RuntimeException::class, 'its own descendant');
});

test('a personal team has no parent', function () {
    $team = Team::factory()->personal()->create();

    expect($team->parent)->toBeNull();
    expect($team->organization_id)->toBeNull();
})->note('Personal teams are removed in PR 2c, which is when the parent becomes required.');
