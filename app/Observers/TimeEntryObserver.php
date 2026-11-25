<?php

namespace App\Observers;

use App\Models\TimeEntry;

class TimeEntryObserver
{
    public function saving(TimeEntry $entry): void
    {
        $entry->organization_id ??= $entry->client->organization_id;
    }
}
