<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Events\BillingChanged;
use App\Observers\WorkTypeObserver;
use Database\Factories\WorkTypeFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperWorkType */
#[UseFactory(WorkTypeFactory::class)]
#[ObservedBy(WorkTypeObserver::class)]
class WorkType extends Model
{
    use BroadcastsChanges, HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('billing')
            ->logOnly(['name', 'color', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function changeBroadcast(): BillingChanged
    {
        return new BillingChanged($this->organization_id);
    }
}
