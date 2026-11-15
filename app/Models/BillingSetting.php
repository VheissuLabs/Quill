<?php

namespace App\Models;

use App\Observers\BillingSettingObserver;
use Database\Factories\BillingSettingFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[UseFactory(BillingSettingFactory::class)]
#[ObservedBy(BillingSettingObserver::class)]
class BillingSetting extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'payment_terms_days' => 'integer',
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
            ->logOnly(['invoice_prefix', 'default_rate', 'payment_terms_days', 'tax_rate'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
