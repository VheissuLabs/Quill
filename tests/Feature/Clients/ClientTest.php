<?php

use App\Models\Client;
use App\Models\Organization;

test('a client belongs to an organization', function () {
    $organization = Organization::factory()->create();

    $client = Client::factory()->for($organization)->create();

    expect($client->organization->id)->toBe($organization->id);
    expect($organization->clients->pluck('id')->all())->toBe([$client->id]);
});

test('a client generates a slug from its name', function () {
    $client = Client::factory()->create(['name' => 'Acme Title Co']);

    expect($client->slug)->toBe('acme-title-co');
});

test('renaming a client regenerates its slug', function () {
    $client = Client::factory()->create(['name' => 'Before']);

    $client->update(['name' => 'After']);

    expect($client->fresh()->slug)->toBe('after');
});

test('client slugs use the next available suffix', function () {
    Client::factory()->create(['name' => 'Acme']);

    $client = Client::factory()->create(['name' => 'Acme']);

    expect($client->slug)->toBe('acme-1');
});

test('a client is resolved by slug', function () {
    expect((new Client)->getRouteKeyName())->toBe('slug');
});

test('a client has a uuid key', function () {
    $client = Client::factory()->create();

    expect(Str::isUuid($client->id))->toBeTrue();
});

test('clients soft delete', function () {
    $client = Client::factory()->create();

    $client->delete();

    $this->assertSoftDeleted('clients', ['id' => $client->id]);
});

test('deleting an organization deletes its clients', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->for($organization)->create();

    $organization->forceDelete();

    $this->assertDatabaseMissing('clients', ['id' => $client->id]);
});

test('the trashed factory state creates a soft deleted client', function () {
    $client = Client::factory()->trashed()->create();

    $this->assertSoftDeleted('clients', ['id' => $client->id]);
});
