<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueSlugs;
use App\Observers\ProjectOwnerObserver;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperProject */

#[ObservedBy(ProjectOwnerObserver::class)]
#[UseFactory(ProjectFactory::class)]
class Project extends Model
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function defaultForClients(): HasMany
    {
        return $this->hasMany(Client::class, 'default_project_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Project $project) {
            if (empty($project->slug)) {
                $project->slug = static::generateUniqueSlug($project->name);
            }
        });

        static::updating(function (Project $project) {
            if ($project->isDirty('name')) {
                $project->slug = static::generateUniqueSlug($project->name, $project->id);
            }
        });
    }
}
