<?php

namespace App\Data;

use App\Enums\FeatureStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\ProjectVisibility;
use App\Enums\SprintStatus;
use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * One activity log entry, with its changed attributes turned into readable "from → to" pairs.
 */
readonly class ActivitySummary
{
    /**
     * @param  array<int, array{field: string, from: string|null, to: string|null}>  $changes
     */
    public function __construct(
        public int $id,
        public string $event,
        public ?string $causerName,
        public string $createdAt,
        public array $changes,
    ) {
        //
    }

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
     * Summarise the latest entries for a subject.
     *
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, self>
     */
    public static function collection(Collection $activities): Collection
    {
        return $activities->map(fn (Activity $activity) => self::fromActivity($activity));
    }

    /**
     * Turn an attribute name into a label.
     */
    protected static function label(string $attribute): string
    {
        return match ($attribute) {
            'sprint_id' => 'Sprint',
            'board_column_id' => 'Column',
            'assignee_id' => 'Assignee',
            'team_id' => 'Team',
            'client_id' => 'Client',
            'starts_at' => 'Start date',
            'ends_at' => 'End date',
            default => Str::headline($attribute),
        };
    }

    /**
     * Turn a stored value into something readable: enum labels, related model names, dates.
     */
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
            'starts_at', 'ends_at' => Str::of((string) $value)->substr(0, 10)->toString(),
            'description' => Str::limit((string) $value, 80),
            default => (string) $value,
        };
    }

    /**
     * Look up the name of a related model by id, falling back when it no longer exists.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    protected static function nameOf(string $model, mixed $id, string $fallback): string
    {
        $name = $model::query()->whereKey($id)->value('name');

        return is_string($name) ? $name : $fallback;
    }
}
