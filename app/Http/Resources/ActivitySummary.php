<?php

namespace App\Http\Resources;

use App\Enums\FeatureStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\ProjectVisibility;
use App\Enums\SprintStatus;
use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

readonly class ActivitySummary
{
    /** @param  array<int, array{field: string, from: string|null, to: string|null}>  $changes */
    public function __construct(
        public int $id,
        public string $event,
        public ?string $causerName,
        public string $createdAt,
        public array $changes,
    ) {}

    public static function fromActivity(Activity $activity): self
    {
        /** @var array<string, mixed> $attributes */
        $attributes = (array) ($activity->attribute_changes ?? collect())->get('attributes', []);
        /** @var array<string, mixed> $old */
        $old = (array) ($activity->attribute_changes ?? collect())->get('old', []);

        $changes = collect($attributes)
            ->reject(fn ($value, string $attribute) => $activity->event === 'updated' && ! array_key_exists($attribute, $old))
            ->map(fn ($value, string $attribute) => [
                'field' => self::label($attribute),
                'from' => $activity->event === 'updated' ? self::display($attribute, $old[$attribute] ?? null) : null,
                'to' => self::display($attribute, $value),
            ])
            ->values()
            ->all();

        return new self(
            id: $activity->id,
            event: $activity->event ?? $activity->description,
            causerName: $activity->causer instanceof User ? $activity->causer->name : null,
            createdAt: $activity->created_at->toISOString(),
            changes: $changes,
        );
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, self>
     */
    public static function collection(Collection $activities): Collection
    {
        return $activities->map(fn (Activity $activity) => self::fromActivity($activity));
    }

    protected static function label(string $attribute): string
    {
        return match ($attribute) {
            'sprint_id' => 'Sprint',
            'board_column_id' => 'Column',
            'assignee_id' => 'Assignee',
            'team_id' => 'Team',
            'client_id' => 'Client',
            'organization_id' => 'Organization',
            'user_id' => 'Member',
            'role_id' => 'Role',
            'feature_id' => 'Feature',
            'parent_id' => 'Reply to',
            'invited_by' => 'Invited by',
            'is_internal' => 'Internal',
            'direction' => 'Vote',
            'email_verified_at' => 'Email verified',
            'two_factor_confirmed_at' => 'Two-factor confirmed',
            'current_organization_id' => 'Current organization',
            'current_team_id' => 'Current team',
            'starts_at' => 'Start date',
            'ends_at' => 'End date',
            'merged_from' => 'Merged from',
            'members_added' => 'Members added',
            'projects_moved' => 'Projects moved',
            'sprints_moved' => 'Sprints moved',
            'issues_moved' => 'Issues moved',
            default => Str::headline($attribute),
        };
    }

    protected static function display(string $attribute, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($attribute) {
            'status' => FeatureStatus::tryFrom((string) $value)?->label()
                ?? IssueStatus::tryFrom((string) $value)?->label()
                ?? SprintStatus::tryFrom((string) $value)?->label()
                ?? (string) $value,
            'priority' => IssuePriority::tryFrom((string) $value)?->label() ?? (string) $value,
            'visibility' => ProjectVisibility::tryFrom((string) $value)?->label() ?? (string) $value,
            'sprint_id' => self::nameOf(Sprint::class, $value, __('a deleted sprint')),
            'board_column_id' => self::nameOf(BoardColumn::class, $value, __('a deleted column')),
            'assignee_id' => self::nameOf(User::class, $value, __('a deleted user')),
            'team_id' => self::nameOf(Team::class, $value, __('a deleted team')),
            'client_id' => self::nameOf(Client::class, $value, __('a deleted client')),
            'organization_id' => self::nameOf(Organization::class, $value, __('a deleted organization')),
            'user_id' => self::nameOf(User::class, $value, __('a deleted user')),
            'feature_id' => self::nameOf(Feature::class, $value, __('a deleted request'), 'title'),
            'parent_id' => self::nameOf(Comment::class, $value, __('a deleted comment'), 'body'),
            'invited_by' => self::nameOf(User::class, $value, __('a deleted user')),
            'current_organization_id' => self::nameOf(Organization::class, $value, __('a deleted organization')),
            'current_team_id' => self::nameOf(Team::class, $value, __('a deleted team')),
            'role_id' => self::nameOf(Role::class, $value, __('a deleted role')),
            'direction' => match ((int) $value) {
                1 => __('Upvote'),
                -1 => __('Downvote'),
                default => (string) $value,
            },
            'is_internal' => $value ? __('Yes') : __('No'),
            'starts_at', 'ends_at', 'expires_at', 'accepted_at', 'verified_at', 'email_verified_at', 'two_factor_confirmed_at' => Str::of((string) $value)->substr(0, 10)->toString(),
            'body' => Str::limit((string) $value, 80),
            'description' => Str::limit((string) $value, 80),
            default => (string) $value,
        };
    }

    /** @param  class-string<Model>  $model */
    protected static function nameOf(string $model, mixed $id, string $fallback, string $column = 'name'): string
    {
        $name = $model::query()->whereKey($id)->value($column);

        return is_string($name) ? Str::limit($name, 80) : $fallback;
    }
}
