<?php

namespace App\Actions\Comments;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class IssueCommentCommands extends CommentCommands
{
    /** @return array<int, array{name: string, usage: string, description: string}> */
    public function availableTo(?User $user, Model $commentable): array
    {
        if (! $user || ! $commentable instanceof Issue || ! $user->can('comment', $commentable)) {
            return [];
        }

        if (! $user->can('update', $commentable)) {
            return [];
        }

        return [
            ['name' => 'status', 'usage' => '/status <backlog|done|cancelled>', 'description' => 'Change the status'],
            ['name' => 'assign', 'usage' => '/assign <name|me|none>', 'description' => 'Set the assignee'],
            ['name' => 'sprint', 'usage' => '/sprint <name|none>', 'description' => 'Plan into a sprint'],
            ['name' => 'points', 'usage' => '/points <number|none>', 'description' => 'Set the estimate'],
            ['name' => 'priority', 'usage' => '/priority <none|low|medium|high|urgent>', 'description' => 'Set the priority'],
        ];
    }

    /** @return array{text: ?string, message: ?string, internal: bool} */
    protected function run(Model $commentable, User $user, string $command, string $argument): array
    {
        if (! $commentable instanceof Issue) {
            $this->unknown($command);
        }

        $message = match ($command) {
            'status' => $this->status($commentable, $argument),
            'assign' => $this->assign($commentable, $user, $argument),
            'sprint' => $this->sprint($commentable, $argument),
            'points' => $this->points($commentable, $argument),
            'priority' => $this->priority($commentable, $argument),
            default => $this->unknown($command),
        };

        return ['text' => null, 'message' => $message, 'internal' => false];
    }

    private function status(Issue $issue, string $argument): string
    {
        $this->authorize('update', $issue, __('You can\'t change this issue.'));

        $allowed = [IssueStatus::Backlog, IssueStatus::Done, IssueStatus::Cancelled];

        $status = collect($allowed)->first(
            fn (IssueStatus $status) => strcasecmp($status->value, $argument) === 0
                || strcasecmp($status->label(), $argument) === 0
        );

        if (! $status) {
            throw ValidationException::withMessages(['body' => __('Unknown status ":status". Use one of: :statuses.', [
                'status' => $argument,
                'statuses' => collect($allowed)->map(fn (IssueStatus $status) => strtolower($status->label()))->implode(', '),
            ])]);
        }

        $issue->changeStatus($status);

        return __('Status set to :status.', ['status' => $status->label()]);
    }

    private function assign(Issue $issue, User $user, string $argument): string
    {
        $this->authorize('update', $issue, __('You can\'t change this issue.'));

        if (strcasecmp($argument, 'none') === 0 || $argument === '') {
            $issue->update(['assignee_id' => null]);

            return __('Assignee cleared.');
        }

        if (strcasecmp($argument, 'me') === 0) {
            $issue->update(['assignee_id' => $user->getKey()]);

            return __('Assigned to :name.', ['name' => $user->name]);
        }

        $matches = $issue->project->organization->members()
            ->get()
            ->filter(fn (User $member) => str_contains(strtolower($member->name), strtolower($argument)));

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['body' => $matches->isEmpty()
                ? __('No member matches ":name".', ['name' => $argument])
                : __('":name" matches more than one member.', ['name' => $argument])]);
        }

        $issue->update(['assignee_id' => $matches->first()->getKey()]);

        return __('Assigned to :name.', ['name' => $matches->first()->name]);
    }

    private function sprint(Issue $issue, string $argument): string
    {
        $this->authorize('update', $issue, __('You can\'t change this issue.'));

        if (strcasecmp($argument, 'none') === 0 || $argument === '') {
            $issue->planInto(null);

            return __('Moved to the backlog.');
        }

        $sprint = Sprint::query()
            ->onBoardFor($issue->project->organization, null)
            ->where('status', '!=', 'completed')
            ->get()
            ->filter(fn (Sprint $sprint) => $sprint->runsSprints())
            ->first(fn (Sprint $sprint) => strcasecmp($sprint->name, $argument) === 0);

        if (! $sprint) {
            throw ValidationException::withMessages(['body' => __('No open sprint named ":name".', ['name' => $argument])]);
        }

        $issue->planInto($sprint);

        return __('Planned into :sprint.', ['sprint' => $sprint->name]);
    }

    private function points(Issue $issue, string $argument): string
    {
        $this->authorize('update', $issue, __('You can\'t change this issue.'));

        if (strcasecmp($argument, 'none') === 0 || $argument === '') {
            $issue->update(['points' => null]);

            return __('Estimate cleared.');
        }

        if (! ctype_digit($argument)) {
            throw ValidationException::withMessages(['body' => __('Points must be a whole number.')]);
        }

        $issue->update(['points' => (int) $argument]);

        return __('Estimate set to :points.', ['points' => (int) $argument]);
    }

    private function priority(Issue $issue, string $argument): string
    {
        $this->authorize('update', $issue, __('You can\'t change this issue.'));

        $priority = collect(IssuePriority::cases())->first(
            fn (IssuePriority $priority) => strcasecmp($priority->value, $argument) === 0
        );

        if (! $priority) {
            throw ValidationException::withMessages(['body' => __('Unknown priority ":priority". Use one of: :priorities.', [
                'priority' => $argument,
                'priorities' => collect(IssuePriority::cases())->map(fn (IssuePriority $priority) => $priority->value)->implode(', '),
            ])]);
        }

        $issue->update(['priority' => $priority]);

        return __('Priority set to :priority.', ['priority' => $priority->value]);
    }
}
