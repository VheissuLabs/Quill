<?php

namespace App\Models;

use App\Concerns\GeneratesOrganizationScopedSlugs;
use App\Concerns\RunsABoard;
use App\Enums\IssueStatus;
use App\Observers\TeamObserver;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
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
 * @property-read Collection<int, Issue> $issues
 */
#[Fillable(['organization_id', 'name', 'slug'])]
#[ObservedBy(TeamObserver::class)]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesOrganizationScopedSlugs, HasFactory, LogsActivity, RunsABoard;

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
     * Move everything this team owns into another team of the same organization, then delete it.
     *
     * Issues follow their status onto the new board, the rule Issue::planInto() already uses when
     * work is planned into another team's sprint. Work whose status has no column over there
     * returns to the backlog rather than landing in a column that means something else.
     *
     * The move is written in bulk, so the issues, sprints and memberships it touches record no
     * activity of their own; the target team gets one entry describing the merge instead.
     */
    public function mergeInto(self $target): void
    {
        if ($target->is($this) || $target->organization_id !== $this->organization_id) {
            throw new InvalidArgumentException('A team can only be merged into another team in the same organization.');
        }

        DB::transaction(function () use ($target) {
            $projectIds = $this->projects()->pluck('projects.id');
            $issuesMoved = $this->issues()->count();

            $attached = $target->members()->syncWithoutDetaching($this->members()->pluck('users.id'))['attached'];
            $target->projects()->syncWithoutDetaching($projectIds);
            $this->projects()->detach();

            $sprintsMoved = $this->sprints()->update([
                'owner_type' => $target->getMorphClass(),
                'owner_id' => $target->getKey(),
            ]);

            $this->moveIssuesTo($target);

            User::where('current_team_id', $this->id)->update(['current_team_id' => $target->id]);

            activity('teams')
                ->on($target)
                ->event('merged')
                ->withChanges(['attributes' => [
                    'merged_from' => $this->name,
                    'members_added' => count($attached),
                    'projects_moved' => $projectIds->count(),
                    'sprints_moved' => $sprintsMoved,
                    'issues_moved' => $issuesMoved,
                ]])
                ->log('merged');

            $this->delete();
        });
    }

    /**
     * Get the issues the team is working on.
     *
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Repoint the team's issues at another team's board, column by column.
     */
    private function moveIssuesTo(self $target): void
    {
        $targetColumns = $target->columns()->get();

        foreach ($this->columns()->get() as $column) {
            $destination = $targetColumns->firstWhere('status', $column->status);

            if ($destination) {
                $this->issues()->where('board_column_id', $column->id)
                    ->update(['board_column_id' => $destination->id, 'team_id' => $target->id]);

                continue;
            }

            $this->issues()->where('board_column_id', $column->id)
                ->whereNotIn('status', [IssueStatus::Done->value, IssueStatus::Cancelled->value])
                ->update(['status' => IssueStatus::Backlog->value]);

            $this->issues()->where('board_column_id', $column->id)
                ->update(['board_column_id' => null, 'team_id' => $target->id]);
        }

        $this->issues()->update(['team_id' => $target->id]);
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
