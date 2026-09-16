<?php

arch('resources')
    ->expect('App\Http\Resources')
    ->toHaveMethod('toArray');
