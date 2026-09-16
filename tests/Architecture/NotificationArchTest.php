<?php

arch('notifications')
    ->expect('App\Notifications')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue')
    ->toHaveMethod('via');
