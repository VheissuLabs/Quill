<?php

arch('models')
    ->expect('App\Models')
    ->toHaveAttribute('Illuminate\Database\Eloquent\Attributes\UseFactory')
    ->toHaveAttribute('Illuminate\Database\Eloquent\Attributes\ObservedBy');
