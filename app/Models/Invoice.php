<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Enums\AdjustmentType;
use App\Enums\InvoiceStatus;
use App\Enums\SquareCheckoutStatus;
use App\Events\InvoicesChanged;
use App\Observers\InvoiceObserver;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperInvoice */
#[UseFactory(InvoiceFactory::class)]
#[ObservedBy(InvoiceObserver::class)]
class Invoice extends Model
{
    use BroadcastsChanges, HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'cc_emails' => 'array',
            'issue_date' => 'date',
            'due_date' => 'date',
            'service_period_start' => 'date',
            'service_period_end' => 'date',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_type' => AdjustmentType::class,
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'deposit_type' => AdjustmentType::class,
            'deposit_value' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'sent_at' => 'datetime',
            'voided_at' => 'datetime',
            'closes_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Sprint, $this> */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    /** @return BelongsTo<ClientContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'contact_id');
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<SquareCheckout, $this> */
    public function squareCheckouts(): HasMany
    {
        return $this->hasMany(SquareCheckout::class);
    }

    /** @return HasMany<InvoicePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function recalculate(): void
    {
        if ($this->status === InvoiceStatus::Void) {
            return;
        }

        $subtotal = round((float) $this->lines()->sum('amount'), 2);
        $taxAmount = round($subtotal * (float) $this->tax_rate / 100, 2);
        $discountAmount = round($this->adjustment($this->discount_type, $this->discount_value, $subtotal), 2);
        $total = round($subtotal + $taxAmount - $discountAmount, 2);
        $amountPaid = round((float) $this->payments()->sum('amount'), 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'amount_paid' => $amountPaid,
            'deposit_amount' => round($this->adjustment($this->deposit_type, $this->deposit_value, $total), 2),
            'status' => $this->derivedStatus($total, $amountPaid),
        ])->save();
    }

    public function takeMissingRecipientsFromClient(): void
    {
        $this->contact_id ??= $this->client->billing_contact_id;

        if (blank($this->cc_emails)) {
            $this->cc_emails = $this->client->billing_cc_emails;
        }

        $this->save();
    }

    public function amountDue(): string
    {
        return number_format(max(0, (float) $this->total - (float) $this->amount_paid), 2, '.', '');
    }

    public function hasPendingSquareCheckout(): bool
    {
        return $this->squareCheckouts()->where('status', SquareCheckoutStatus::Pending)->exists();
    }

    public function canPayOnline(): bool
    {
        return $this->organization->billingSettings()->squareConfigured()
            && $this->sent_at !== null
            && ! in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::Void, InvoiceStatus::Paid], true)
            && (float) $this->amountDue() > 0
            && ! $this->hasPendingSquareCheckout();
    }

    public function defaultLineRate(): float|int|string|null
    {
        return $this->project?->billingRate()
            ?? $this->client->billing_rate
            ?? $this->organization->billingSettings()->default_rate;
    }

    public function isEditable(): bool
    {
        return $this->sent_at === null
            && $this->status !== InvoiceStatus::Paid
            && $this->status !== InvoiceStatus::Void;
    }

    public function publicUrl(): string
    {
        return route('invoices.public', $this->public_token);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('invoices')
            ->logOnly([
                'number', 'status', 'client_id', 'contact_id', 'issue_date', 'due_date',
                'tax_rate', 'discount_type', 'discount_value', 'deposit_type', 'deposit_value',
                'subtotal', 'total', 'amount_paid', 'sent_at', 'voided_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    private function adjustment(?AdjustmentType $type, float|int|string|null $value, float $base): float
    {
        return match ($type) {
            AdjustmentType::Percentage => $base * (float) $value / 100,
            AdjustmentType::Fixed => (float) $value,
            null => 0.0,
        };
    }

    private function derivedStatus(float $total, float $amountPaid): InvoiceStatus
    {
        return match (true) {
            $total > 0 && $amountPaid >= $total => InvoiceStatus::Paid,
            $amountPaid > 0 => InvoiceStatus::Partial,
            $this->sent_at === null => InvoiceStatus::Draft,
            $this->status === InvoiceStatus::Overdue => InvoiceStatus::Overdue,
            default => InvoiceStatus::Sent,
        };
    }

    public function changeBroadcast(): InvoicesChanged
    {
        return new InvoicesChanged($this->organization_id);
    }
}
