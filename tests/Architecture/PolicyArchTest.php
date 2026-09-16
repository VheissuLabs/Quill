<?php

use Illuminate\Support\Str;

arch('policies')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

test('every policy matches a model Laravel can discover, or is registered by hand', function () {
    $orphans = collect(glob(dirname(__DIR__, 2).'/app/Policies/*.php'))
        ->map(fn (string $file) => Str::before(basename($file, '.php'), 'Policy'))
        ->reject(fn (string $model) => class_exists('App\Models\\'.$model))
        ->values()
        ->all();

    expect($orphans)->toBe(['Role']);
});
