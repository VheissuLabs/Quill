<?php

namespace App\Models;

use App\Concerns\GeneratesOrganizationScopedSlugs;
use App\Concerns\RunsABoard;
use App\Enums\IssueStatus;
use App\Observers\TeamObserver;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperTeam */
#[UseFactory(TeamFactory::class)]
#[ObservedBy(TeamObserver::class)]
class Team extends Model
{
    use GeneratesOrganizationScopedSlugs, HasFactory, LogsActivity, RunsABoard;

    protected $guarded = [];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsToMany<User, $this, TeamMembership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->using(TeamMembership::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany<TeamMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_teams')
            ->withTimestamps();
    }

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
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

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
