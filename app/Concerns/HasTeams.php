<?php

namespace App\Concerns;

use App\Data\UserTeam;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

trait HasTeams
{
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withTimestamps();
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function personalTeam(): ?Team
    {
        return $this->teams()
            ->where('is_personal', true)
            ->first();
    }

    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    public function ownsTeam(Team $team): bool
    {
        return $team->owner_id === $this->getKey();
    }

    /**
     * A team's structural parent may be an organization or a client, but it always
     * carries the organization it belongs to, so scoping is a plain filter rather
     * than a walk up the parent chain.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false, ?Organization $organization = null): Collection
    {
        return $this->teams()
            ->with('parent')
            ->when($organization, fn ($query) => $query->where('teams.organization_id', $organization->id))
            ->get()
            ->map(fn (Team $team) => ! $includeCurrent && $this->isCurrentTeam($team) ? null : $this->toUserTeam($team))
            ->filter()
            ->values();
    }

    public function toUserTeam(Team $team): UserTeam
    {
        $parent = $team->parent;

        /**
         * `morphTo()` is typed as returning a bare Model, so the concrete parent is
         * narrowed here rather than by promising a tighter generic the framework
         * does not actually return.
         */
        $hasNamedParent = $parent instanceof Organization || $parent instanceof Client;

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isPersonal: $team->is_personal,
            isOwner: $team->owner_id === $this->getKey(),
            parentName: $hasNamedParent ? $parent->name : null,
            parentType: $hasNamedParent ? Str::lower(class_basename($parent)) : null,
            isCurrent: $this->isCurrentTeam($team),
        );
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();
    }
}
