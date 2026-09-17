<?php

arch('controllers')
    ->expect(value: 'App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller')
    ->toHaveLineCountLessThan(100)
    ->not->toUse([
        'abort_unless',
        'abort_if',
        'abort',
        'Illuminate\Http\Request',
        'Illuminate\Support\Facades\Gate',
        'Illuminate\Contracts\Auth\Access\Gate',
        'Illuminate\Foundation\Auth\Access\AuthorizesRequests',
    ])
    ->not->toHavePrivateMethods()
    ->not->toHaveProtectedMethods();
