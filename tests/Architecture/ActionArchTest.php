<?php

arch('actions')
    ->expect('App\Actions')
    ->toHaveMethod('handle')
    ->ignoring('App\Actions\Fortify');

arch('actions raise domain failures rather than http ones')
    ->expect('App\Actions')
    ->not->toUse([
        'abort',
        'abort_if',
        'abort_unless',
        'auth',
        'Illuminate\Support\Facades\Auth',
    ]);
