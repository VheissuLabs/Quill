<?php

namespace App\Observers;

use App\Models\WorkType;

class WorkTypeObserver
{
    public function saving(WorkType $workType): void
    {
        $workType->name = trim($workType->name);
    }

    public function creating(WorkType $workType): void
    {
        if (! $workType->isDirty('sort_order')) {
            $workType->sort_order = ((int) WorkType::where('organization_id', $workType->organization_id)->max('sort_order')) + 1;
        }
    }
}
