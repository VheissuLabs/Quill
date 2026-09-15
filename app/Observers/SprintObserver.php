<?php

namespace App\Observers;

use App\Models\Sprint;

class SprintObserver
{
    public function deleting(Sprint $sprint): void
    {
        $sprint->issues()->each(fn ($issue) => $issue->planInto(null));
    }
}
