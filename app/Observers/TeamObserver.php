<?php

namespace App\Observers;

use App\Models\Team;

class TeamObserver
{
    /**
     * Every team runs its own board; a new team starts with the default columns.
     */
    public function created(Team $team): void
    {
        $team->createDefaultColumns();
    }
}
