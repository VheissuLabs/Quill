<?php

namespace App\Observers;

use App\Models\Project;
use App\Support\RichText;

class ProjectObserver
{
    public function saving(Project $project): void
    {
        if ($project->isDirty('description') && $project->description !== null) {
            $project->description = RichText::sanitize($project->description);
        }
    }
}
