<?php

namespace App\Models;

use App\Actions\Billing\CancelSquareCheckouts;
use App\Concerns\BroadcastsChanges;
use App\Enums\SquareCheckoutStatus;
use App\Events\BillingChanged;
use App\Observers\BillingSettingObserver;
use Database\Factories\BillingSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperBillingSetting */
#[Hidden(['square_access_token', 'square_webhook_signature_key'])]
#[UseFactory(BillingSettingFactory::class)]
#[ObservedBy(BillingSettingObserver::class)]
class BillingSetting extends Model
{
    use BroadcastsChanges, HasFactory, LogsActivity;

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

    /** @param  array<string, mixed>  $attributes */
    public function saveSettings(array $attributes): void
    {
        $environment = $attributes['square_environment'] ?? $this->square_environment;

        if ($environment !== $this->square_environment) {
            $this->organization->invoices()
                ->whereHas('squareCheckouts', fn ($query) => $query->where('status', SquareCheckoutStatus::Open))
                ->get()
                ->each(function (Invoice $invoice): void {
                    $invoice->setRelation('organization', $this->organization);
                    app(CancelSquareCheckouts::class)->handle($invoice);
                });
        }

        $this->update($attributes);
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

    public function changeBroadcast(): BillingChanged
    {
        return new BillingChanged($this->organization_id);
    }
}
