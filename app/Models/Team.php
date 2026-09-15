<?php

namespace App\Models;

use App\Concerns\GeneratesOrganizationScopedSlugs;
use App\Concerns\RunsABoard;
use App\Enums\IssueStatus;
use App\Observers\TeamObserver;
use Database\Factories\TeamFactory;
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
#[ObservedBy(TeamObserver::class)]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesOrganizationScopedSlugs, HasFactory, LogsActivity, RunsABoard;

    protected $guarded = [];

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
     * The target is another team, or the organization taking the work back when the team is the
     * last one standing or simply not wanted any more. Handing back to the organization drops the
     * team restriction on its private projects and leaves everyone a plain organization member.
     *
     * The move is written in bulk, so the issues, sprints and memberships it touches record no
     * activity of their own; the target gets one entry describing the merge instead.
     */
    public function mergeInto(Organization|self $target): void
    {
        $targetOrganization = $target instanceof self ? $target->organization_id : $target->getKey();

        if ($target->is($this) || $targetOrganization !== $this->organization_id) {
            throw new InvalidArgumentException('A team can only be merged into its own organization or another team in it.');
        }

        DB::transaction(function () use ($target) {
            $projectIds = $this->projects()->pluck('projects.id');
            $issuesMoved = $this->issues()->count();

            $attached = $target instanceof self
                ? $target->members()->syncWithoutDetaching($this->members()->pluck('users.id'))['attached']
                : [];

            if ($target instanceof self) {
                $target->projects()->syncWithoutDetaching($projectIds);
            }

            $this->projects()->detach();

            $sprintsMoved = $this->sprints()->update([
                'owner_type' => $target->getMorphClass(),
                'owner_id' => $target->getKey(),
            ]);

            $this->moveIssuesTo($target);

            User::where('current_team_id', $this->id)
                ->update(['current_team_id' => $target instanceof self ? $target->getKey() : null]);

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
     * Repoint the team's issues at the target's board, column by column.
     */
    private function moveIssuesTo(Organization|self $target): void
    {
        $teamId = $target instanceof self ? $target->getKey() : null;
        $targetColumns = $target->columns()->get();

        foreach ($this->columns()->get() as $column) {
            $destination = $targetColumns->firstWhere('status', $column->status);

            if ($destination) {
                $this->issues()->where('board_column_id', $column->id)
                    ->update(['board_column_id' => $destination->id, 'team_id' => $teamId]);

                continue;
            }

            $this->issues()->where('board_column_id', $column->id)
                ->whereNotIn('status', [IssueStatus::Done->value, IssueStatus::Cancelled->value])
                ->update(['status' => IssueStatus::Backlog->value]);

            $this->issues()->where('board_column_id', $column->id)
                ->update(['board_column_id' => null, 'team_id' => $teamId]);
        }

        $this->issues()->update(['team_id' => $teamId]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('teams')
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
