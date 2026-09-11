<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueOrganizationSlugs;
use App\Enums\OrganizationRole;
use Closure;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The tenant. Members hold a role per organization, stored through spatie/laravel-permission
 * with the organization acting as the permission "team".
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, OrganizationInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Client> $clients
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Issue> $issues
 * @property-read Collection<int, Sprint> $sprints
 */
#[Fillable(['name', 'slug'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use GeneratesUniqueOrganizationSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = static::generateUniqueOrganizationSlug($organization->name);
            }
        });

        static::updating(function (Organization $organization) {
            if ($organization->isDirty('name')) {
                $organization->slug = static::generateUniqueOrganizationSlug($organization->name, $organization->id);
            }
        });
    }

    /**
     * Get the organization owner.
     */
    public function owner(): ?User
    {
        return $this->members()
            ->whereHas('organizationRoles', fn ($query) => $query
                ->where('name', OrganizationRole::Owner->value)
                ->where('model_has_roles.organization_id', $this->id))
            ->first();
    }

    /**
     * Get all members of this organization.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id', 'user_id')
            ->using(Membership::class)
            ->withTimestamps();
    }

    /**
     * Get all memberships for this organization.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this organization.
     *
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Get the teams (departments) of the organization.
     *
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the clients of the organization.
     *
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Get the projects of the organization.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the issues across all of the organization's projects.
     *
     * @return HasManyThrough<Issue, Project, $this>
     */
    public function issues(): HasManyThrough
    {
        return $this->hasManyThrough(Issue::class, Project::class);
    }

    /**
     * Get the sprints across all of the organization's teams.
     *
     * @return HasManyThrough<Sprint, Team, $this>
     */
    public function sprints(): HasManyThrough
    {
        return $this->hasManyThrough(Sprint::class, Team::class);
    }

    /**
     * Add the user to the organization with the given role.
     */
    public function addMember(User $user, OrganizationRole $role): void
    {
        DB::transaction(function () use ($user, $role) {
            $this->memberships()->firstOrCreate(['user_id' => $user->id]);

            $this->changeMemberRole($user, $role);
        });
    }

    /**
     * Replace the user's role within the organization.
     */
    public function changeMemberRole(User $user, OrganizationRole $role): void
    {
        $this->asPermissionsTeam(fn () => $user->syncRoles($role));

        $user->unsetRelation('organizationRoles');
    }

    /**
     * Remove the user, and the role they held, from the organization.
     */
    public function removeMember(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->asPermissionsTeam(fn () => $user->syncRoles([]));

            $this->memberships()->where('user_id', $user->id)->delete();
        });

        $user->unsetRelation('organizationRoles');
    }

    /**
     * Run the callback with this organization as the permission team context.
     */
    protected function asPermissionsTeam(Closure $callback): mixed
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($this->id);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
