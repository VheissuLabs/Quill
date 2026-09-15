<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueOrganizationSlugs;
use App\Enums\OrganizationPermission;
use App\Observers\OrganizationObserver;
use Closure;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
 * @property-read Collection<int, OrganizationMembership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Client> $clients
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Issue> $issues
 * @property-read Collection<int, Sprint> $sprints
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['name', 'slug'])]
#[ObservedBy(OrganizationObserver::class)]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use GeneratesUniqueOrganizationSlugs, HasFactory, LogsActivity, SoftDeletes;

    /**
     * Create the locked owner role. It is the only role an organization starts with; every other
     * role is defined by the organization itself. The flag, not the name, marks it as the owner
     * role: it always holds every permission, cannot be renamed, deleted or edited, only owners
     * can grant it, and the organization always keeps at least one member holding it.
     */
    public function createOwnerRole(): void
    {
        $this->roles()->firstOrCreate(['is_owner' => true], ['name' => 'Owner', 'guard_name' => 'web'])
            ->syncPermissions(array_map(fn (OrganizationPermission $permission) => $permission->value, OrganizationPermission::cases()));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Get the roles this organization defines.
     *
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, config('permission.column_names.team_foreign_key'));
    }

    /**
     * Get one of the organization's roles by name.
     */
    public function role(string $name): ?Role
    {
        return $this->roles()->where('name', $name)->first();
    }

    /**
     * Get the locked owner role.
     */
    public function ownerRole(): Role
    {
        return $this->roles()->where('is_owner', true)->firstOrFail();
    }

    /**
     * Get the roles that can be granted to members: every role except the locked owner role.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(): Collection
    {
        return $this->roles()->where('is_owner', false)->orderBy('name')->get();
    }

    /**
     * Determine if the user holds the locked owner role in this organization.
     */
    public function isOwner(User $user): bool
    {
        return (bool) $user->organizationRole($this)?->is_owner;
    }

    /**
     * Get the members who hold the owner role.
     *
     * @return Collection<int, User>
     */
    public function owners(): Collection
    {
        return $this->members()
            ->whereHas('organizationRoles', fn ($query) => $query
                ->where('is_owner', true)
                ->where('model_has_roles.organization_id', $this->id))
            ->get();
    }

    /**
     * Determine if the organization would still have an owner without the given user. Every
     * organization keeps at least one owner, so the last one cannot leave, be removed or be demoted.
     */
    public function hasOtherOwner(User $user): bool
    {
        return $this->owners()->contains(fn (User $owner) => $owner->isNot($user));
    }

    /**
     * Determine if the actor may change or end the owner's membership: an owner's membership is
     * theirs alone to change, and never when they are the last owner.
     */
    public function ownerMayStepDown(User $actor, User $owner): bool
    {
        return $actor->is($owner) && $this->hasOtherOwner($owner);
    }

    /**
     * Get all members of this organization.
     *
     * @return BelongsToMany<User, $this, OrganizationMembership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id', 'user_id')
            ->using(OrganizationMembership::class)
            ->withTimestamps();
    }

    /**
     * Get all memberships for this organization.
     *
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
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
     * Add the user to the organization with the given role, one of this organization's roles
     * or its name.
     */
    public function addMember(User $user, Role|string $role): void
    {
        DB::transaction(function () use ($user, $role) {
            $this->memberships()->firstOrCreate(['user_id' => $user->id]);

            $this->changeMemberRole($user, $role);
        });
    }

    /**
     * Replace the user's role within the organization.
     */
    public function changeMemberRole(User $user, Role|string $role): void
    {
        $role = $this->resolveRole($role);

        $this->asPermissionsTeam(fn () => $user->syncRoles($role));

        $user->unsetRelation('organizationRoles');
    }

    /**
     * Turn a role name into one of this organization's roles, refusing roles of other organizations.
     */
    protected function resolveRole(Role|string $role): Role
    {
        if (is_string($role)) {
            return $this->roles()->where('name', $role)->firstOrFail();
        }

        abort_unless((int) $role->getAttribute(config('permission.column_names.team_foreign_key')) === $this->id, 404);

        return $role;
    }

    /**
     * Remove the user, and the role they held, from the organization.
     */
    public function removeMember(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->asPermissionsTeam(fn () => $user->syncRoles([]));

            $this->memberships()->where('user_id', $user->id)->first()?->delete();
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

    /**
     * Log renames and slug changes.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('organizations')
            ->logOnly(['name', 'slug'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
