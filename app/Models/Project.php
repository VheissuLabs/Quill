<?php

namespace App\Models;

use App\Enums\OrganizationPermission;
use App\Enums\ProjectVisibility;
use App\Observers\OrganizationScopedSlugObserver;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property-read int|null $open_features_count
 * @property-read Organization $organization
 *
 * @mixin IdeHelperProject
 */
#[UseFactory(ProjectFactory::class)]
#[ObservedBy(OrganizationScopedSlugObserver::class)]
class Project extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_teams')
            ->withTimestamps();
    }

    /** @return HasMany<Feature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /** @return HasMany<Issue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function isPublic(): bool
    {
        return $this->visibility === ProjectVisibility::Public;
    }

    /** @param  array<int, int>  $teamIds */
    public function syncTeams(array $teamIds): void
    {
        $this->teams()->sync($this->isPublic() ? [] : $teamIds);
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('visibility', ProjectVisibility::Public);

            if (! $user) {
                return;
            }

            $query->orWhere(fn (Builder $query) => $query
                ->where('visibility', ProjectVisibility::Private)
                ->where(fn (Builder $query) => $query
                    ->whereIn('organization_id', $user->organizationIdsWithPermission(OrganizationPermission::ManageProjects))
                    ->orWhere(fn (Builder $query) => $query
                        ->whereHas('organization.members', fn (Builder $query) => $query->whereKey($user->id))
                        ->where(fn (Builder $query) => $query
                            ->whereDoesntHave('teams')
                            ->orWhereHas('teams.members', fn (Builder $query) => $query->whereKey($user->id))))));
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('projects')
            ->logOnly(['name', 'description', 'visibility', 'client_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visibility' => ProjectVisibility::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
