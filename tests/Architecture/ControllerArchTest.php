<?php

arch('controllers')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller')
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Support\Facades\Gate',
        'Illuminate\Contracts\Auth\Access\Gate',
        'Illuminate\Foundation\Auth\Access\AuthorizesRequests',
    ])
    ->toHaveLineCountLessThan(100)
    ->not->toHavePrivateMethods()
    ->not->toHaveProtectedMethods();
