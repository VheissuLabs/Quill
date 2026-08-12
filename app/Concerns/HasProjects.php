<?php

namespace App\Concerns;

use App\Data\UserProject;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait HasProjects
{
    /**
     * The projects in the organization the user is working in.
     *
     * Eager loads the owner: the sidebar names it for every row, and without this
     * a ten-project organization costs ten extra queries on every page.
     *
     * @return Collection<int, UserProject>
     */
    public function toUserProjects(?Organization $organization = null): Collection
    {
        $organization ??= $this->currentOrganization;

        if ($organization === null || ! $this->belongsToOrganization($organization)) {
            return collect();
        }

        return $organization->projects()
            ->with('owner')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => $this->toUserProject($project));
    }

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
