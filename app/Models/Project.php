<?php

namespace App\Models;

use App\Concerns\GeneratesOrganizationScopedSlugs;
use App\Enums\OrganizationPermission;
use App\Enums\ProjectVisibility;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $client_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property ProjectVisibility $visibility
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Client|null $client
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Feature> $features
 * @property-read Collection<int, Issue> $issues
 * @property-read int|null $features_count
 * @property-read int|null $open_features_count
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use GeneratesOrganizationScopedSlugs, HasFactory, LogsActivity;

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

    /**
     * Get the teams a private project is restricted to. An empty set means every organization member.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_teams')
            ->withTimestamps();
    }

    /**
     * Get the feature requests for the project.
     *
     * @return HasMany<Feature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /** @return HasMany<Issue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Determine if the project is visible to anyone.
     */
    public function isPublic(): bool
    {
        return $this->visibility === ProjectVisibility::Public;
    }

    /**
     * Scope the query to projects the given user may view.
     *
     * Public projects are visible to everyone. Private projects are visible to organization
     * members who can manage projects, and otherwise only to members of the restricted teams
     * (or any organization member when no teams are attached).
     *
     * @param  Builder<$this>  $query
     */
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

    /**
     * Record project setting changes.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('projects')
            ->logOnly(['name', 'description', 'visibility', 'client_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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
