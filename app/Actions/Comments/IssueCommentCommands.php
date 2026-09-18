<?php

namespace App\Actions\Comments;

use App\Enums\IssueLinkType;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Sprint;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
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
            ['name' => 'parent', 'usage' => '/parent <issue id>', 'description' => 'Make this the parent of another issue'],
            ['name' => 'child', 'usage' => '/child <issue id>', 'description' => 'Make this a child of another issue'],
            ['name' => 'blocks', 'usage' => '/blocks <issue id>', 'description' => 'Mark this as blocking another issue'],
            ['name' => 'relates', 'usage' => '/relates <issue id>', 'description' => 'Relate another issue'],
            ['name' => 'duplicates', 'usage' => '/duplicates <issue id>', 'description' => 'Mark this as duplicating another'],
            ['name' => 'clones', 'usage' => '/clones <issue id>', 'description' => 'Mark this as cloning another'],
            ['name' => 'unlink', 'usage' => '/unlink <issue id>', 'description' => 'Remove every link to another issue'],
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
            'parent' => $this->link($commentable, IssueLinkType::Parent, $argument),
            'child' => $this->linkFrom($commentable, IssueLinkType::Parent, $argument),
            'blocks' => $this->link($commentable, IssueLinkType::Blocks, $argument),
            'relates' => $this->link($commentable, IssueLinkType::Relates, $argument),
            'duplicates' => $this->link($commentable, IssueLinkType::Duplicates, $argument),
            'clones' => $this->link($commentable, IssueLinkType::Clones, $argument),
            'unlink' => $this->unlink($commentable, $argument),
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

    private function link(Issue $issue, IssueLinkType $type, string $argument): string
    {
        $this->authorize('update', $issue, __('You can\'t change this issue.'));

        $other = $this->resolve($issue, $argument);

        try {
            $issue->linkTo($other, $type);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['body' => $exception->getMessage()]);
        }

        return __(':label :reference.', ['label' => $type->label(), 'reference' => $other->reference()]);
    }

    /** The other issue becomes the parent, so the row is stored the other way round. */
    private function linkFrom(Issue $issue, IssueLinkType $type, string $argument): string
    {
        $this->authorize('update', $issue, __('You can\'t change this issue.'));

        $other = $this->resolve($issue, $argument);

        try {
            $other->linkTo($issue, $type);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['body' => $exception->getMessage()]);
        }

        return __(':label :reference.', ['label' => $type->inverseLabel(), 'reference' => $other->reference()]);
    }

    private function unlink(Issue $issue, string $argument): string
    {
        $this->authorize('update', $issue, __('You can\'t change this issue.'));

        $other = $this->resolve($issue, $argument);

        return $issue->unlinkFrom($other) > 0
            ? __('Unlinked :reference.', ['reference' => $other->reference()])
            : __('No link to :reference.', ['reference' => $other->reference()]);
    }

    private function resolve(Issue $issue, string $argument): Issue
    {
        $id = (int) ltrim(trim($argument), '#');

        $other = $id > 0
            ? Issue::query()
                ->whereKey($id)
                ->whereHas('project', fn (Builder $query) => $query
                    ->where('organization_id', $issue->project->organization_id))
                ->first()
            : null;

        if (! $other) {
            throw ValidationException::withMessages(['body' => __('No issue :id in this organization.', ['id' => $argument])]);
        }

        return $other;
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
