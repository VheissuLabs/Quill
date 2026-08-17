<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueSlugs;
use App\Observers\OrganizationObserver;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperOrganization */

#[UseFactory(OrganizationFactory::class)]
#[ObservedBy(OrganizationObserver::class)]
class Organization extends Model
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

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id', 'user_id')
            ->using(OrganizationMembership::class)
            ->withPivot(['client_id'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * Every client in the organization, at any depth. Distinct from
     * `childClients()`, which is only those the organization holds directly.
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function childClients(): MorphMany
    {
        return $this->morphMany(Client::class, 'parent');
    }

    /** Every team in the organization, at any depth. */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function childTeams(): MorphMany
    {
        return $this->morphMany(Team::class, 'parent');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function issueTypes(): HasMany
    {
        return $this->hasMany(IssueType::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = static::generateUniqueSlug($organization->name);
            }
        });

        static::updating(function (Organization $organization) {
            if ($organization->isDirty('name')) {
                $organization->slug = static::generateUniqueSlug($organization->name, $organization->id);
            }
        });
    }
}
