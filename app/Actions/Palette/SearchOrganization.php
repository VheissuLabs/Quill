<?php

namespace App\Actions\Palette;

use App\Enums\OrganizationCapability;
use App\Models\Client;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class SearchOrganization
{
    private const PER_TYPE = 5;

    private const MINIMUM_TERM = 2;

    /** @return Collection<int, Client|Feature|Issue|Project|Sprint|Team> */
    public function handle(Organization $organization, User $user, string $term): Collection
    {
        if (mb_strlen($term) < self::MINIMUM_TERM) {
            return collect();
        }

        $projectIds = $organization->projects()->visibleTo($user)->pluck('projects.id');

        return $this->projects($organization, $user, $term)
            ->concat($organization->canUse(OrganizationCapability::Issues) ? $this->issues($projectIds, $term) : [])
            ->concat($organization->canUse(OrganizationCapability::Boards) ? $this->features($projectIds, $term) : [])
            ->concat($organization->canUse(OrganizationCapability::Sprints) ? $this->sprints($organization, $term) : [])
            ->concat($this->teams($organization, $term))
            ->concat($this->clients($organization, $term));
    }

    /** @return Collection<int, Project> */
    private function projects(Organization $organization, User $user, string $term): Collection
    {
        return $organization->projects()
            ->visibleTo($user)
            ->where('name', 'like', $this->wildcard($term))
            ->with(['organization', 'client'])
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return Collection<int, Issue>
     */
    private function issues(Collection $projectIds, string $term): Collection
    {
        return Issue::query()
            ->whereIn('project_id', $projectIds)
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', $this->wildcard($term))
                ->when(ctype_digit($term), fn (Builder $query) => $query->orWhere('number', (int) $term)))
            ->with('project.organization')
            ->tap(fn (Builder $query) => $this->rank($query, 'title', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /**
     * @param  Collection<int, int>  $projectIds
     * @return Collection<int, Feature>
     */
    private function features(Collection $projectIds, string $term): Collection
    {
        return Feature::query()
            ->whereIn('project_id', $projectIds)
            ->where('title', 'like', $this->wildcard($term))
            ->with('project.organization')
            ->tap(fn (Builder $query) => $this->rank($query, 'title', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, Sprint> */
    private function sprints(Organization $organization, string $term): Collection
    {
        return Sprint::query()
            ->onBoardFor($organization, null)
            ->where('name', 'like', $this->wildcard($term))
            ->with(['owner' => function (Relation $owner): void {
                if ($owner instanceof MorphTo) {
                    $owner->morphWith([Team::class => ['organization']]);
                }
            }])
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, Team> */
    private function teams(Organization $organization, string $term): Collection
    {
        return $organization->teams()
            ->where('name', 'like', $this->wildcard($term))
            ->with('organization')
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @return Collection<int, Client> */
    private function clients(Organization $organization, string $term): Collection
    {
        return $organization->clients()
            ->where('name', 'like', $this->wildcard($term))
            ->with('organization')
            ->tap(fn (Builder $query) => $this->rank($query, 'name', $term))
            ->limit(self::PER_TYPE)
            ->get();
    }

    /** @param  Builder<covariant Model>  $query */
    private function rank(Builder $query, string $column, string $term): void
    {
        $query->orderByRaw(
            $column === 'title' ? 'INSTR(LOWER(title), ?)' : 'INSTR(LOWER(name), ?)',
            [mb_strtolower($term)],
        )->orderBy($column);
    }

    private function wildcard(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
