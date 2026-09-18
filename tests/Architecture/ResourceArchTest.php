<?php

arch('resources')
    ->expect('App\Http\Resources')
    ->toHaveMethod('toArray')
    ->not->toUse('Illuminate\Support\Facades\DB');
