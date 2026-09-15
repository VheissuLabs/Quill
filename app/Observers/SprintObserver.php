<?php

namespace App\Observers;

use App\Models\Sprint;

class SprintObserver
{
    /**
     * Unplan the sprint's issues before it goes.
     *
     * The foreign key only clears sprint_id, which would leave the issues sitting in a board
     * column of a sprint that no longer exists while the backlog counts them as unplanned.
     */
    public function deleting(Sprint $sprint): void
    {
        $sprint->issues()->each(fn ($issue) => $issue->planInto(null));
    }
}
