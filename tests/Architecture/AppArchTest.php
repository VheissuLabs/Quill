<?php

arch()
    ->preset()
    ->laravel();

arch()
    ->preset()
    ->security();

arch()
    ->expect('App')
    ->toBeCasedCorrectly()
    ->not->toUse(['env', 'dd', 'dump', 'ray']);

test('app keeps the directories it has agreed on', function () {
    $directories = collect(glob(dirname(__DIR__, 2).'/app/*', GLOB_ONLYDIR))
        ->map(fn (string $path) => basename($path))
        ->values()
        ->all();

    expect($directories)->toBe([
        'Actions',
        'Concerns',
        'Data',
        'Enums',
        'Events',
        'Http',
        'Models',
        'Notifications',
        'Observers',
        'Policies',
        'Providers',
        'Rules',
        'Support',
    ]);
});

test('closures do not reach outside themselves to change what they find', function () {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app')
    );

    $offenders = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        if (preg_match('/\buse\s*\([^)]*&\s*\$/', $contents) === 1) {
            $offenders[] = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});
