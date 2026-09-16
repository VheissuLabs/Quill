<?php

arch('controllers leave authorization to policies')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\Gate',
        'Illuminate\Contracts\Auth\Access\Gate',
        'Illuminate\Foundation\Auth\Access\AuthorizesRequests',
    ]);
