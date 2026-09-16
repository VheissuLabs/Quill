<?php

arch('middleware')
    ->expect('App\Http\Middleware')
    ->toHaveMethod('handle')
    ->toOnlyBeUsedIn(['App\Providers', 'App\Http\Middleware']);
