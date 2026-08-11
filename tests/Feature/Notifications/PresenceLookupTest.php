<?php

use App\Contracts\PresenceLookup;
use App\Models\User;
use App\Support\FakePresenceLookup;
use App\Support\ReverbPresenceLookup;

test('the container resolves the reverb implementation by default', function () {
    expect(app(PresenceLookup::class))->toBeInstanceOf(ReverbPresenceLookup::class);
});

test('the fake reports whatever it was told', function () {
    $user = User::factory()->create();

    $fake = new FakePresenceLookup(online: true);
    expect($fake->isOnline($user))->toBeTrue();

    $fake->setOnline(false);
    expect($fake->isOnline($user))->toBeFalse();
});

test('the fake can be swapped into the container', function () {
    $user = User::factory()->create();

    app()->instance(PresenceLookup::class, new FakePresenceLookup(online: true));

    expect(app(PresenceLookup::class)->isOnline($user))->toBeTrue();
});

test('an unreachable reverb reports the user as offline so the email still sends', function () {
    $user = User::factory()->create();

    config()->set('broadcasting.connections.reverb.options.port', 1);

    expect(app(ReverbPresenceLookup::class)->isOnline($user))->toBeFalse();
})->note('Fail-open: a WebSocket outage must cost a redundant email, never a swallowed notification.');
