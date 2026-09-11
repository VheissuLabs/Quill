<?php

namespace Database\Seeders;

use App\Actions\Issues\CreateIssue;
use App\Enums\FeatureStatus;
use App\Enums\IssuePriority;
use App\Enums\OrganizationRole;
use App\Enums\ProjectVisibility;
use App\Enums\SprintStatus;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Seeds a small, realistic workspace for local development around Karl's VheissuLabs
 * organization (teams, a public board full of requests, a private board) and the NotaryDash
 * organization with a whitelabel client.
 * Safe to rerun.
 */
class DemoSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = $this->user('Karl Murray', 'karl@vheissulabs.com');
        $voters = $this->voters();

        $organization = Organization::firstOrCreate(['slug' => 'vheissulabs'], ['name' => 'VheissuLabs']);
        $organization->addMember($owner, OrganizationRole::Owner);
        $owner->switchOrganization($organization);

        $development = $organization->teams()->firstOrCreate(['slug' => 'development'], ['name' => 'Development']);
        $organization->teams()->firstOrCreate(['slug' => 'marketing'], ['name' => 'Marketing']);
        $development->members()->syncWithoutDetaching([$owner->id]);

        $gitDifferent = $organization->projects()->firstOrCreate(['slug' => 'gitdifferent'], [
            'name' => 'GitDifferent',
            'description' => 'Code review that gets out of your way. Tell us what would make it better.',
            'visibility' => ProjectVisibility::Public,
        ]);

        $roadmap = $organization->projects()->firstOrCreate(['slug' => 'internal-roadmap'], [
            'name' => 'Internal Roadmap',
            'description' => 'Ideas from the Development team, before they are ready for customers.',
            'visibility' => ProjectVisibility::Private,
        ]);
        $roadmap->teams()->syncWithoutDetaching([$development->id]);

        foreach ($this->gitDifferentRequests() as $index => $request) {
            $this->feature($gitDifferent, $request, $index, $owner, $voters);
        }

        $this->seedSprint($development, $gitDifferent, $owner);

        $this->seedNotaryDash($owner);
    }

    /**
     * Give the Development team an active sprint with the two most wanted GitDifferent requests
     * converted into issues.
     */
    protected function seedSprint(Team $team, Project $project, User $owner): void
    {
        $sprint = $team->sprints()->firstOrCreate(['name' => 'Sprint 1'], [
            'starts_at' => now()->startOfWeek(),
            'ends_at' => now()->startOfWeek()->addWeeks(2)->subDay(),
            'status' => SprintStatus::Active,
        ]);

        $features = $project->features()
            ->whereIn('title', ['Stacked pull requests', 'Dark mode for the diff viewer'])
            ->whereDoesntHave('issue')
            ->get();

        foreach ($features as $feature) {
            $issue = app(CreateIssue::class)->handle($project, $owner, [
                'title' => $feature->title,
                'description' => $feature->description,
                'priority' => IssuePriority::High,
                'points' => 5,
                'assignee_id' => $owner->id,
            ], $feature);

            $issue->planInto($sprint);
        }
    }

    /**
     * Seed the NotaryDash organization: an internal board plus a private board for a whitelabel client.
     */
    protected function seedNotaryDash(User $owner): void
    {
        $organization = Organization::firstOrCreate(['slug' => 'notarydash'], ['name' => 'NotaryDash']);
        $organization->addMember($owner, OrganizationRole::Owner);

        $organization->teams()->firstOrCreate(['slug' => 'development'], ['name' => 'Development']);
        $organization->teams()->firstOrCreate(['slug' => 'support'], ['name' => 'Support']);

        $client = $organization->clients()->firstOrCreate(['slug' => 'whitelabel-co'], ['name' => 'Whitelabel Co']);

        $organization->projects()->firstOrCreate(['slug' => 'notarydash'], [
            'name' => 'NotaryDash',
            'description' => 'Feature requests for the NotaryDash platform.',
            'visibility' => ProjectVisibility::Private,
        ]);

        $organization->projects()->firstOrCreate(['slug' => 'whitelabel-portal'], [
            'name' => 'Whitelabel Portal',
            'client_id' => $client->id,
            'description' => 'Requests from Whitelabel Co for their branded portal.',
            'visibility' => ProjectVisibility::Private,
        ]);
    }

    /**
     * Get or create a user with the well-known demo password.
     */
    protected function user(string $name, string $email): User
    {
        return User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
        ]);
    }

    /**
     * Get the people who vote and comment on the demo board.
     *
     * @return Collection<int, User>
     */
    protected function voters(): Collection
    {
        return collect(['Ada Lovelace', 'Grace Hopper', 'Linus Torvalds', 'Margaret Hamilton', 'Dennis Ritchie'])
            ->map(fn (string $name) => $this->user($name, Str::of($name)->slug('.').'@example.com'));
    }

    /**
     * Create a feature request with its votes and comments, unless it already exists.
     *
     * @param  array{title: string, description: string, status: FeatureStatus, votes: int, comments: int}  $request
     * @param  Collection<int, User>  $voters
     */
    protected function feature(Project $project, array $request, int $index, User $owner, Collection $voters): void
    {
        if ($project->features()->where('title', $request['title'])->exists()) {
            return;
        }

        $author = $index % 3 === 0 ? $owner : $voters[$index % $voters->count()];

        $feature = Feature::factory()
            ->for($project)
            ->for($author, 'author')
            ->create([
                'title' => $request['title'],
                'description' => $request['description'],
                'status' => $request['status'],
                'created_at' => now()->subDays(21 - $index * 3),
            ]);

        foreach ($voters->take($request['votes']) as $voter) {
            $feature->votes()->create(['user_id' => $voter->id]);
        }

        $bodies = [
            'This would save my team hours every week.',
            'Agree, but please make it opt-in per repository.',
            'Any update on the timeline for this one?',
        ];

        foreach (array_slice($bodies, 0, $request['comments']) as $bodyIndex => $body) {
            $feature->comments()->create([
                'user_id' => $voters[$bodyIndex]->id,
                'body' => $body,
            ]);
        }
    }

    /**
     * The requests on the public GitDifferent board.
     *
     * @return array<int, array{title: string, description: string, status: FeatureStatus, votes: int, comments: int}>
     */
    protected function gitDifferentRequests(): array
    {
        return [
            [
                'title' => 'Stacked pull requests',
                'description' => "Let me open a PR that targets another open PR and have the base move automatically when the parent merges.\n\nToday I rebase three times a day and reviewers lose the diff context every time.",
                'status' => FeatureStatus::Planned,
                'votes' => 5,
                'comments' => 2,
            ],
            [
                'title' => 'Inline AI review summaries',
                'description' => 'A collapsible summary at the top of every PR that explains what changed and why, generated from the diff and the commit messages.',
                'status' => FeatureStatus::Open,
                'votes' => 4,
                'comments' => 1,
            ],
            [
                'title' => 'Dark mode for the diff viewer',
                'description' => 'The rest of the app is dark, but the diff viewer flashes white. Please match the theme and keep the syntax colors readable.',
                'status' => FeatureStatus::InProgress,
                'votes' => 3,
                'comments' => 3,
            ],
            [
                'title' => 'Draft PRs that skip CI',
                'description' => 'Drafts should not burn CI minutes until I mark them ready. A toggle per repository would be enough.',
                'status' => FeatureStatus::Open,
                'votes' => 3,
                'comments' => 0,
            ],
            [
                'title' => 'Keyboard shortcut to jump between comments',
                'description' => 'n / p to move to the next and previous unresolved comment thread, like GitHub does with j / k for files.',
                'status' => FeatureStatus::Completed,
                'votes' => 2,
                'comments' => 1,
            ],
            [
                'title' => 'Blame view with commit grouping',
                'description' => 'Group consecutive lines from the same commit and show the PR that introduced them, not just the SHA.',
                'status' => FeatureStatus::Open,
                'votes' => 1,
                'comments' => 0,
            ],
            [
                'title' => 'Export a PR as a patch email',
                'description' => 'Some upstreams still want patches by mail. One click to produce a git format-patch series from a PR.',
                'status' => FeatureStatus::Declined,
                'votes' => 0,
                'comments' => 1,
            ],
        ];
    }
}
