<?php

arch('listeners')
    ->expect('App\Listeners')
    ->toHaveMethod('handle')
    ->toOnlyBeUsedIn('App\Providers');
