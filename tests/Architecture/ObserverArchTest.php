<?php

arch('observers')
    ->expect('App\Observers')
    ->toHaveSuffix('Observer')
    ->toOnlyBeUsedIn('App\Models');
