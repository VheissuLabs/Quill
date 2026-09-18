<?php

use Spatie\LaravelData\Data;

arch('data')
    ->expect('App\Data')
    ->toHaveSuffix('Data')
    ->toExtend(Data::class);
