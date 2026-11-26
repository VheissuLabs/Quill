<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Enums\LineUnit;
use App\Events\InvoicesChanged;
use App\Observers\InvoiceLineObserver;
use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperInvoiceLine */
#[UseFactory(InvoiceLineFactory::class)]
#[ObservedBy(InvoiceLineObserver::class)]
class InvoiceLine extends Model
{
    use BroadcastsChanges, HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit' => LineUnit::class,
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<WorkType, $this> */
    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    /** @return BelongsTo<Issue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderBy('worked_on')->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('invoices')
            ->logOnly(['description', 'quantity', 'unit', 'rate', 'amount', 'work_type_id', 'issue_id', 'reference_url'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function changeBroadcast(): InvoicesChanged
    {
        $this->loadMissing('invoice');

        return new InvoicesChanged($this->invoice->organization_id);
    }
}
