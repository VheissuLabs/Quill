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

/** @mixin IdeHelperBillingSetting */
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
            'square_access_token' => 'encrypted',
            'square_webhook_signature_key' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function squareConfigured(): bool
    {
        return filled($this->square_access_token) && filled($this->square_location_id);
    }

    public function webhookUrl(): string
    {
        if (blank($this->square_webhook_token)) {
            $this->save();
        }

        return rtrim(config('app.url'), '/').'/webhooks/square/'.$this->square_webhook_token;
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
