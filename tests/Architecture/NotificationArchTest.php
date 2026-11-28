<?php

arch('notifications')
    ->expect('App\Notifications')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue')
    ->toHaveAttribute('Illuminate\Queue\Attributes\DeleteWhenMissingModels')
    ->toHaveMethod('via');
