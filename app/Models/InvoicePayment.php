<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Observers\InvoicePaymentObserver;
use Database\Factories\InvoicePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[UseFactory(InvoicePaymentFactory::class)]
#[ObservedBy(InvoicePaymentObserver::class)]
class InvoicePayment extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('invoices')
            ->logOnly(['amount', 'paid_on', 'method', 'reference', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
