<?php

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

test('the default broadcast connection is reverb', function () {
    expect(config('broadcasting.default'))->toBe('reverb');
});

test('the reverb connection resolves to a pusher broadcaster', function () {
    expect(Broadcast::connection('reverb'))->toBeInstanceOf(PusherBroadcaster::class);
});

test('the reverb connection points at herd', function () {
    $options = config('broadcasting.connections.reverb.options');

    expect($options['host'])->toBe('127.0.0.1');
    expect((int) $options['port'])->toBe(8080);
    expect($options['scheme'])->toBe('http');
    expect($options['useTLS'])->toBeFalse();
});

test('the broadcasting auth route is registered', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri());

    expect($uris)->toContain('broadcasting/auth');
})->note('The framework registers this route unnamed, so it is found by URI, not by name.');
