<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Feature;

class FeatureObserver
{
    public function deleting(Feature $feature): void
    {
        Comment::withoutEvents(
            fn () => $feature->comments()->cursor()->each(fn (Comment $comment) => $comment->delete())
        );
    }
}
