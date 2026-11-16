<?php

namespace App\Models;

use App\Actions\Billing\RemoveAutomaticBilling;
use App\Concerns\RunsABoard;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationModule;
use App\Enums\OrganizationPermission;
use App\Events\ModulesChanged;
use App\Observers\OrganizationObserver;
use Closure;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @mixin IdeHelperOrganization */
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
                }

                if (! preg_match('/^'.preg_quote($defaultSlug, '/').'-(\d+)$/', $slug, $matches)) {
                    return null;
                }

                return (int) $matches[1];
            })
            ->filter(fn (?int $suffix) => $suffix !== null)
            ->max() ?? 0;

        return $existingSlugs->isEmpty()
            ? $defaultSlug
            : $defaultSlug.'-'.($maxSuffix + 1);
    }

    public function createOwnerRole(): void
    {
        $this->roles()->firstOrCreate(['name' => 'Owner'], ['guard_name' => 'web'])
            ->syncPermissions(array_map(fn (OrganizationPermission $permission) => $permission->value, OrganizationPermission::cases()));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    public function urlAfterSwitching(string $previous): string
    {
        $dashboard = route('dashboard', ['current_organization' => $this]);

        try {
            $route = Route::getRoutes()->match(Request::create($previous));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return $dashboard;
        }

        $parameters = $route->parameterNames();

        if (! in_array('current_organization', $parameters, true)) {
            return $previous;
        }

        if (count($parameters) > 1 || ! $route->getName()) {
            return $dashboard;
        }

        $query = parse_url($previous, PHP_URL_QUERY);

        return route($route->getName(), ['current_organization' => $this]).($query ? "?{$query}" : '');
    }

    public static function supportInbox(): ?self
    {
        return self::query()->firstWhere('slug', config('services.resend.support_organization'));
    }

    public function receivesSupportMail(): bool
    {
        return $this->slug === config('services.resend.support_organization');
    }

    public function allowsInvoiceManagementBy(User $user): bool
    {
        return $this->canUse(OrganizationCapability::Invoices)
            && $user->hasOrganizationPermission($this, OrganizationPermission::ManageInvoices);
    }

    public function billingSettings(): BillingSetting
    {
        return $this->billingSettingsRecord()->firstOrCreate([], [
            'invoice_prefix' => 'INV',
            'payment_terms_days' => 30,
        ]);
    }

    /** @return HasOne<BillingSetting, $this> */
    public function billingSettingsRecord(): HasOne
    {
        return $this->hasOne(BillingSetting::class);
    }

    /** @return HasMany<WorkType, $this> */
    public function workTypes(): HasMany
    {
        return $this->hasMany(WorkType::class);
    }

    /** @return HasMany<ClientContact, $this> */
    public function clientContacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
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
        $cyclesWereUsable = $this->canUse(OrganizationCapability::BillingCycles);

        $this->capabilitySettings()->upsert(
            collect(OrganizationModule::cases())
                ->flatMap(function (OrganizationModule $module) use ($settings, $now) {
                    $enabled = (bool) ($settings[$module->value.'_enabled'] ?? true);
                    $justEnabled = $enabled && ! $this->isModuleEnabled($module);

                    return collect($module->capabilities())
                        ->map(fn (OrganizationCapability $capability) => [
                            'capability' => $capability->value,
                            'enabled' => $justEnabled || (bool) ($settings[$module->value.'_'.$capability->value.'_enabled'] ?? true),
                        ])
                        ->prepend(['capability' => '', 'enabled' => $enabled])
                        ->map(fn (array $row) => [
                            ...$row,
                            'organization_id' => $this->id,
                            'module' => $module->value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                })
                ->all(),
            ['organization_id', 'module', 'capability'],
            ['enabled', 'updated_at'],
        );

        $this->unsetRelation('capabilitySettings');

        if ($cyclesWereUsable && ! $this->canUse(OrganizationCapability::BillingCycles)) {
            app(RemoveAutomaticBilling::class)->handle($this);
        }

        broadcast(new ModulesChanged($this->getKey()))->toOthers();
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

    /** @return HasMany<Role, $this> */
    public function rolesWithMembers(): HasMany
    {
        return $this->roles()
            ->with(['permissions', 'users' => fn ($query) => $query->orderBy('name')])
            ->withCount('users')
            ->orderByRaw("CASE WHEN name = 'Owner' THEN 0 ELSE 1 END")
            ->orderBy('name');
    }

    public function role(string $name): ?Role
    {
        return $this->roles()->where('name', $name)->first();
    }

    public function billingTimezone(): string
    {
        $ownerRoleId = $this->roles()->where('name', 'Owner')->value('id');

        $timezone = DB::table('organization_members')
            ->join('users', 'users.id', '=', 'organization_members.user_id')
            ->join('model_has_roles', function ($join) use ($ownerRoleId) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', (new User)->getMorphClass())
                    ->where('model_has_roles.role_id', $ownerRoleId);
            })
            ->where('organization_members.organization_id', $this->id)
            ->orderBy('organization_members.created_at')
            ->orderBy('organization_members.id')
            ->value('users.timezone');

        return $timezone ?? config('app.timezone');
    }

    public function ownerRole(): Role
    {
        return $this->roles()->where('name', 'Owner')->firstOrFail();
    }

    public function isOwnerRole(Role $role): bool
    {
        return $role->name === 'Owner';
    }

    /** @return Collection<int, Role> */
    public function assignableRoles(): Collection
    {
        return $this->roles()->where('name', '!=', 'Owner')->orderBy('name')->get();
    }

    /** @return Collection<int, Role> */
    public function rolesVisibleTo(User $user): Collection
    {
        return $this->roles()
            ->when(! $this->isOwner($user), fn ($query) => $query->where('name', '!=', 'Owner'))
            ->orderByRaw("CASE WHEN name = 'Owner' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
    }

    public function renameTo(string $name): self
    {
        return DB::transaction(function () use ($name) {
            $organization = self::whereKey($this->id)->lockForUpdate()->firstOrFail();

            $organization->update(['name' => $name]);

            return $organization;
        });
    }

    public function deleteWith(User $actor): void
    {
        DB::transaction(function () use ($actor) {
            User::where('current_organization_id', $this->id)
                ->where('id', '!=', $actor->id)
                ->each(fn (User $affected) => $affected->switchToFallbackOrganization($this));

            $this->invitations()->delete();
            $this->members->each(fn (User $member) => $this->removeMember($member));
            $this->delete();
        });
    }

    public function isOwner(User $user): bool
    {
        $role = $user->organizationRole($this);

        return $role !== null && $this->isOwnerRole($role);
    }

    /** @return Collection<int, User> */
    public function owners(): Collection
    {
        return $this->members()
            ->whereHas('organizationRoles', fn ($query) => $query
                ->where('name', 'Owner')
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

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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

        if ((int) $role->getAttribute(config('permission.column_names.team_foreign_key')) !== $this->id) {
            throw (new ModelNotFoundException)->setModel(Role::class, [$role->getKey()]);
        }

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
