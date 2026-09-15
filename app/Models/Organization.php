<?php

namespace App\Models;

use App\Concerns\RunsABoard;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationModule;
use App\Enums\OrganizationPermission;
use App\Observers\OrganizationObserver;
use Closure;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
 * @property string|null $logo_path
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, OrganizationInvitation> $invitations
 * @property-read Collection<int, OrganizationMembership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Client> $clients
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Issue> $issues
 * @property-read Collection<int, Sprint> $sprints
 * @property-read Collection<int, BoardColumn> $columns
 * @property-read Collection<int, OrganizationModuleSetting> $capabilitySettings
 * @property-read Collection<int, Role> $roles
 */
#[UseFactory(OrganizationFactory::class)]
#[ObservedBy(OrganizationObserver::class)]
class Organization extends Model
{
    use HasFactory, LogsActivity, RunsABoard, SoftDeletes;

    protected $guarded = [];

    public static function generateUniqueOrganizationSlug(string $name, ?int $excludeId = null): string
    {
        $defaultSlug = Str::slug($name);

        $query = self::withTrashed()
            ->where(function ($query) use ($defaultSlug) {
                $query->where('slug', $defaultSlug)
                    ->orWhere('slug', 'like', $defaultSlug.'-%');
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existingSlugs = $query->pluck('slug');

        $maxSuffix = $existingSlugs
            ->map(function (string $slug) use ($defaultSlug): ?int {
                if ($slug === $defaultSlug) {
                    return 0;
                } elseif (preg_match('/^'.preg_quote($defaultSlug, '/').'-(\d+)$/', $slug, $matches)) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter(fn (?int $suffix) => $suffix !== null)
            ->max() ?? 0;

        return $existingSlugs->isEmpty()
            ? $defaultSlug
            : $defaultSlug.'-'.($maxSuffix + 1);
    }

    public function createOwnerRole(): void
    {
        $this->roles()->firstOrCreate(['is_owner' => true], ['name' => 'Owner', 'guard_name' => 'web'])
            ->syncPermissions(array_map(fn (OrganizationPermission $permission) => $permission->value, OrganizationPermission::cases()));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    public function isModuleEnabled(OrganizationModule $module): bool
    {
        return $this->settingEnabled($module->value, '');
    }

    public function canUse(OrganizationCapability $capability): bool
    {
        return $this->isModuleEnabled($capability->module())
            && $this->settingEnabled($capability->module()->value, $capability->value);
    }

    /** @return array<string, array{enabled: bool, capabilities: array<string, bool>}> */
    public function moduleSettings(): array
    {
        $this->loadMissing('capabilitySettings');

        return collect(OrganizationModule::cases())
            ->mapWithKeys(fn (OrganizationModule $module) => [
                $module->key() => [
                    'enabled' => $this->isModuleEnabled($module),
                    'capabilities' => collect($module->capabilities())
                        ->mapWithKeys(fn (OrganizationCapability $capability) => [
                            $capability->value => $this->canUse($capability),
                        ])
                        ->all(),
                ],
            ])
            ->all();
    }

    /** @return HasMany<OrganizationModuleSetting, $this> */
    public function capabilitySettings(): HasMany
    {
        return $this->hasMany(OrganizationModuleSetting::class);
    }

    public function dissolveTeams(): void
    {
        DB::transaction(fn () => $this->teams()->get()->each(fn (Team $team) => $team->mergeInto($this)));
    }

    /** @param  array<string, bool>  $settings */
    public function updateModuleSettings(array $settings): void
    {
        $now = now();
        $rows = [];

        foreach (OrganizationModule::cases() as $module) {
            $enabled = (bool) ($settings[$module->value.'_enabled'] ?? true);
            $justEnabled = $enabled && ! $this->isModuleEnabled($module);

            $rows[] = [
                'organization_id' => $this->id,
                'module' => $module->value,
                'capability' => '',
                'enabled' => $enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($module->capabilities() as $capability) {
                $rows[] = [
                    'organization_id' => $this->id,
                    'module' => $module->value,
                    'capability' => $capability->value,
                    'enabled' => $justEnabled || (bool) ($settings[$module->value.'_'.$capability->value.'_enabled'] ?? true),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->capabilitySettings()->upsert(
            $rows,
            ['organization_id', 'module', 'capability'],
            ['enabled', 'updated_at'],
        );

        $this->unsetRelation('capabilitySettings');
    }

    private function settingEnabled(string $module, string $capability): bool
    {
        $setting = $this->relationLoaded('capabilitySettings')
            ? $this->capabilitySettings->first(fn (OrganizationModuleSetting $setting) => $setting->module === $module && $setting->capability === $capability)
            : $this->capabilitySettings()->where('module', $module)->where('capability', $capability)->first();

        return $setting === null || $setting->enabled;
    }

    public function replaceLogo(?UploadedFile $logo): void
    {
        $previous = $this->logo_path;

        $this->update([
            'logo_path' => $logo?->storePublicly('organization-logos'),
        ]);

        if ($previous && $previous !== $this->logo_path) {
            Storage::delete($previous);
        }
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, config('permission.column_names.team_foreign_key'));
    }

    public function role(string $name): ?Role
    {
        return $this->roles()->where('name', $name)->first();
    }

    public function ownerRole(): Role
    {
        return $this->roles()->where('is_owner', true)->firstOrFail();
    }

    /** @return Collection<int, Role> */
    public function assignableRoles(): Collection
    {
        return $this->roles()->where('is_owner', false)->orderBy('name')->get();
    }

    public function isOwner(User $user): bool
    {
        return (bool) $user->organizationRole($this)?->is_owner;
    }

    /** @return Collection<int, User> */
    public function owners(): Collection
    {
        return $this->members()
            ->whereHas('organizationRoles', fn ($query) => $query
                ->where('is_owner', true)
                ->where('model_has_roles.organization_id', $this->id))
            ->get();
    }

    public function hasOtherOwner(User $user): bool
    {
        return $this->owners()->contains(fn (User $owner) => $owner->isNot($user));
    }

    public function ownerMayStepDown(User $actor, User $owner): bool
    {
        return $this->isOwner($actor) && $this->hasOtherOwner($owner);
    }

    /** @return BelongsToMany<User, $this, OrganizationMembership, 'pivot'> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id', 'user_id')
            ->using(OrganizationMembership::class)
            ->withTimestamps();
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** @return HasMany<OrganizationInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** @return HasMany<Client, $this> */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasManyThrough<Issue, Project, $this> */
    public function issues(): HasManyThrough
    {
        return $this->hasManyThrough(Issue::class, Project::class);
    }

    /**
     * @param  string  $childType
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveChildRouteBinding($childType, $value, $field): ?Model
    {
        if ($childType === 'sprint') {
            return $this->allSprints()->where($field ?? (new Sprint)->getRouteKeyName(), $value)->first();
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
    }

    /** @return Builder<Sprint> */
    public function allSprints(): Builder
    {
        return Sprint::query()->where(fn (Builder $query) => $query
            ->whereMorphedTo('owner', $this)
            ->orWhere(fn (Builder $query) => $query
                ->where('owner_type', (new Team)->getMorphClass())
                ->whereIn('owner_id', $this->teams()->select('id'))));
    }

    public function addMember(User $user, Role|string $role): void
    {
        DB::transaction(function () use ($user, $role) {
            $this->memberships()->firstOrCreate(['user_id' => $user->id]);

            $this->changeMemberRole($user, $role);
        });
    }

    public function changeMemberRole(User $user, Role|string $role): void
    {
        $role = $this->resolveRole($role);

        $this->asPermissionsTeam(fn () => $user->syncRoles($role));

        $user->unsetRelation('organizationRoles');
    }

    protected function resolveRole(Role|string $role): Role
    {
        if (is_string($role)) {
            return $this->roles()->where('name', $role)->firstOrFail();
        }

        abort_unless((int) $role->getAttribute(config('permission.column_names.team_foreign_key')) === $this->id, 404);

        return $role;
    }

    public function removeMember(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->asPermissionsTeam(fn () => $user->syncRoles([]));

            $this->memberships()->where('user_id', $user->id)->first()?->delete();
        });

        $user->unsetRelation('organizationRoles');
    }

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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('organizations')
            ->logOnly(['name', 'slug'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
