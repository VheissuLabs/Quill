<?php

arch('responses are named for what they are')
    ->expect('App\Http\Responses')
    ->toHaveSuffix('Response')
    ->ignoring('App\Http\Responses\Concerns');

arch('responses are responsable')
    ->expect('App\Http\Responses')
    ->toImplement('Illuminate\Contracts\Support\Responsable')
    ->ignoring('App\Http\Responses\Concerns');
