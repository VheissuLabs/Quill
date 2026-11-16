<?php

namespace App\Models;

use App\Enums\SquareCheckoutStatus;
use App\Observers\SquareCheckoutObserver;
use Database\Factories\SquareCheckoutFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin IdeHelperSquareCheckout */
#[UseFactory(SquareCheckoutFactory::class)]
#[ObservedBy(SquareCheckoutObserver::class)]
class SquareCheckout extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'checked_at' => 'datetime',
            'status' => SquareCheckoutStatus::class,
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
