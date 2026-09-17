<?php

namespace App\Observers;

use App\Models\Feature;

class FeatureObserver
{
    public function deleting(Feature $feature): void
    {
        $feature->comments()->cursor()->each(fn ($comment) => $comment->delete());
    }
}
