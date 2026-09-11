<?php

namespace App\Models;

use App\Concerns\GeneratesOrganizationScopedSlugs;
use App\Enums\IssueStatus;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * A department within an organization, such as Marketing or Development.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, TeamMembership> $memberships
 * @property-read Collection<int, Sprint> $sprints
 * @property-read Collection<int, BoardColumn> $columns
 * @property-read Collection<int, Project> $projects
 */
#[Fillable(['organization_id', 'name', 'slug'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesOrganizationScopedSlugs, HasFactory, LogsActivity;

    /**
     * Every team has a board; a new team starts with the default columns.
     */
    protected static function booted(): void
    {
        static::created(fn (Team $team) => $team->createDefaultColumns());
    }

    /**
     * Get the organization the team belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the members of the team.
     *
     * @return BelongsToMany<User, $this, TeamMembership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->using(TeamMembership::class)
            ->withTimestamps();
    }

    /**
     * Get the team's membership records.
     *
     * @return HasMany<TeamMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    /**
     * Get the private projects restricted to this team.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_teams')
            ->withTimestamps();
    }

    /**
     * Get the board columns of the team, in board order.
     *
     * @return HasMany<BoardColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    /**
     * Create the columns a team starts with: Todo, In progress, Done.
     */
    public function createDefaultColumns(): void
    {
        foreach (IssueStatus::columnStatuses() as $position => $status) {
            $this->columns()->create([
                'name' => $status->label(),
                'status' => $status,
                'position' => $position,
            ]);
        }
    }

    /**
     * Get the sprints run by the team.
     *
     * @return HasMany<Sprint, $this>
     */
    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class);
    }

    /**
     * Record team renames.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('teams')
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
