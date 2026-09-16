<?php

arch()
    ->preset()
    ->laravel();

arch()
    ->preset()
    ->security();

arch()
    ->expect('App')
    ->toBeCasedCorrectly();

test('app keeps the directories it has agreed on', function () {
    $directories = collect(glob(dirname(__DIR__, 2).'/app/*', GLOB_ONLYDIR))
        ->map(fn (string $path) => basename($path))
        ->values()
        ->all();

    expect($directories)->toBe([
        'Actions',
        'Concerns',
        'Enums',
        'Http',
        'Models',
        'Notifications',
        'Observers',
        'Policies',
        'Providers',
        'Rules',
    ]);
});
