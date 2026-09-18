<?php

arch('models')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->toHaveAttribute('Illuminate\Database\Eloquent\Attributes\UseFactory')
    ->toHaveAttribute('Illuminate\Database\Eloquent\Attributes\ObservedBy')
    ->not->toUse([
        'abort_if',
        'abort_unless',
        'abort',
    ]);

test('models put side effects in observers rather than declaring boot or booted', function () {
    $offenders = collect(glob(dirname(__DIR__, 2).'/app/Models/*.php'))
        ->map(fn (string $file) => 'App\Models\\'.basename($file, '.php'))
        ->filter(fn (string $model) => collect(['boot', 'booted'])->contains(
            fn (string $method) => (new ReflectionClass($model))->getMethod($method)->getDeclaringClass()->getName() === $model
        ))
        ->values()
        ->all();

    expect($offenders)->toBeEmpty();
});
