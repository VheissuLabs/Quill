<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueSlugs;
use App\Enums\TeamRole;
use App\Observers\ParentIntegrityObserver;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperTeam */

#[ObservedBy(ParentIntegrityObserver::class)]
#[UseFactory(TeamFactory::class)]
class Team extends Model
{
    use GeneratesUniqueSlugs, HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('organization');
    }

    public function projects(): MorphMany
    {
        return $this->morphMany(Project::class, 'owner');
    }

    /** @return Collection<int, string> */
    public function clientsInScope(): Collection
    {
        $ids = $this->clients()->pluck('id');

        if ($this->getAttribute('parent_type') === Client::class) {
            $ids->push($this->getAttribute('parent_id'));
        }

        return $ids->unique()->values();
    }

    /** @return Builder<Project> */
    public function projectsInScope(): Builder
    {
        $clientIds = $this->clientsInScope();

        return Project::query()
            ->where('organization_id', $this->organization_id)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $owned) => $owned
                    ->where('owner_type', self::class)
                    ->where('owner_id', $this->getKey()))
                ->orWhere(fn (Builder $client) => $client
                    ->where('owner_type', Client::class)
                    ->whereIn('owner_id', $clientIds)));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return MorphTo<Model, $this> */
    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function clients(): MorphMany
    {
        return $this->morphMany(Client::class, 'parent');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'team_members',
            'team_id',
            'user_id'
        )
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        return ! $this->is_personal;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueSlug($team->name, $team->id);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
        ];
    }
}
