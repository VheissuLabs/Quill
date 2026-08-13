<?php

namespace App\Concerns;

use App\Data\UserProject;
use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Str;

trait HasProjects
{
    public function toUserProject(Project $project): UserProject
    {
        $owner = $project->owner;
        $named = $owner instanceof Client || $owner instanceof Team;

        return new UserProject(
            id: $project->id,
            name: $project->name,
            slug: $project->slug,
            ownerName: $named ? $owner->name : null,
            ownerType: $named ? Str::lower(class_basename($owner)) : null,
        );
    }
}
