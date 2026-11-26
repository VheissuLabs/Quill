<?php

namespace App\Observers;

use App\Models\Document;
use App\Models\Issue;
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

    public function deleting(Project $project): void
    {
        Document::query()
            ->where('documentable_type', (new Issue)->getMorphClass())
            ->whereIn('documentable_id', $project->issues()->select('id'))
            ->each(fn (Document $document) => $document->delete());
    }
}
