<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use RuntimeException;

class ProjectOwnerObserver
{
    public function saving(Project $project): void
    {
        $owner = $this->resolveOwner($project);

        if ($owner === null) {
            throw new RuntimeException('A project must be owned by a client or a team.');
        }

        if ($owner->organization_id !== $project->organization_id) {
            throw new RuntimeException(sprintf(
                '[%s] belongs to a different organization than the project.',
                $owner->name,
            ));
        }

        $project->unsetRelation('owner');
    }

    protected function resolveOwner(Project $project): Client|Team|null
    {
        $type = $project->getAttribute('owner_type');
        $id = $project->getAttribute('owner_id');

        if (blank($type) || blank($id)) {
            return null;
        }

        return match ($type) {
            Client::class => Client::whereKey($id)->first(),
            Team::class => Team::whereKey($id)->first(),
            default => throw new RuntimeException(sprintf('[%s] cannot own a project.', $type)),
        };
    }
}
