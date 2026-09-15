<?php

namespace Database\Seeders;

use App\Actions\Issues\CreateIssue;
use App\Enums\FeatureStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\SprintStatus;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Tickets for every board. Planned, in-progress and completed requests become issues placed where
 * their status says (backlog, a running sprint's column, or done in the finished sprint), which
 * also re-derives the request's status from the ticket. Each board then gets a handful of
 * unrelated issues, a few of them planned, one of them cancelled.
 */
class IssueSeeder extends Seeder
{
    public function __construct(protected CreateIssue $createIssue)
    {
        //
    }

    public function run(): void
    {
        Project::with(['organization.members', 'organization.teams.columns', 'organization.teams.sprints'])->get()
            ->each(function (Project $project) {
                $teams = $project->organization->teams;
                $members = $project->organization->members;

                $project->features()
                    ->whereIn('status', [FeatureStatus::Planned, FeatureStatus::InProgress, FeatureStatus::Completed])
                    ->get()
                    ->each(fn (Feature $feature) => $this->convert($feature, $teams, $members));

                for ($count = fake()->numberBetween(3, 6); $count > 0; $count--) {
                    $issue = $this->createIssue->handle($project, $members->random(), [
                        'title' => rtrim(fake()->sentence(fake()->numberBetween(3, 6)), '.'),
                        'description' => fake()->boolean(70) ? fake()->paragraph() : null,
                        'priority' => fake()->randomElement(IssuePriority::cases()),
                        'points' => fake()->boolean(60) ? fake()->randomElement([1, 2, 3, 5, 8]) : null,
                        'assignee_id' => fake()->boolean(50) ? $members->random()->id : null,
                    ]);

                    match (fake()->numberBetween(1, 6)) {
                        1 => $issue->planInto($this->sprint($teams, SprintStatus::Active)),
                        2 => $this->work($issue, $this->sprint($teams, SprintStatus::Active)),
                        3 => $issue->planInto($this->sprint($teams, SprintStatus::Planned) ?? $this->sprint($teams, SprintStatus::Active)),
                        4 => $issue->changeStatus(IssueStatus::Cancelled),
                        default => null,
                    };
                }
            });
    }

    /**
     * Turn a request into an issue and move the issue to where the request's status says it is.
     *
     * @param  Collection<int, Team>  $teams
     * @param  Collection<int, User>  $members
     */
    protected function convert(Feature $feature, Collection $teams, Collection $members): void
    {
        $status = $feature->status;

        $issue = $this->createIssue->handle($feature->project, $members->random(), [
            'title' => $feature->title,
            'description' => $feature->description,
            'priority' => fake()->randomElement([IssuePriority::Medium, IssuePriority::High, IssuePriority::Urgent]),
            'points' => fake()->randomElement([2, 3, 5, 8]),
            'assignee_id' => $members->random()->id,
        ], $feature);

        match ($status) {
            FeatureStatus::Planned => fake()->boolean() ? $issue->planInto($this->sprint($teams, SprintStatus::Active)) : null,
            FeatureStatus::InProgress => $this->work($issue, $this->sprint($teams, SprintStatus::Active)),
            FeatureStatus::Completed => $this->finish($issue, $this->sprint($teams, SprintStatus::Completed) ?? $this->sprint($teams, SprintStatus::Active)),
            default => null,
        };
    }

    /**
     * Plan the issue into the sprint and drop it in one of the in-progress columns.
     */
    protected function work(Issue $issue, ?Sprint $sprint): void
    {
        if (! $sprint) {
            return;
        }

        $issue->planInto($sprint);

        $column = $sprint->team->columns()->where('status', IssueStatus::InProgress)->inRandomOrder()->first();

        if ($column) {
            $issue->moveToColumn($column);
        }
    }

    /**
     * Plan the issue into the sprint and mark it done there.
     */
    protected function finish(Issue $issue, ?Sprint $sprint): void
    {
        if (! $sprint) {
            $issue->changeStatus(IssueStatus::Done);

            return;
        }

        $issue->planInto($sprint);
        $issue->moveToColumn($sprint->team->columns()->where('status', IssueStatus::Done)->firstOrFail());
    }

    /**
     * Pick a sprint in the given state from any of the organization's teams.
     *
     * @param  Collection<int, Team>  $teams
     */
    protected function sprint(Collection $teams, SprintStatus $status): ?Sprint
    {
        return $teams->flatMap->sprints->where('status', $status)->shuffle()->first();
    }
}
