<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Organization */
class InvoiceSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $today = CarbonImmutable::now($this->billingTimezone())->startOfDay();
        $next = $this->invoices()
            ->owing()
            ->with('client:id,name,slug')
            ->orderBy('due_date')
            ->orderBy('id')
            ->first();

        return [
            'nextPayment' => $next === null ? null : [
                'invoiceId' => $next->id,
                'number' => $next->number,
                'clientName' => $next->client->name,
                'amountDue' => $next->amountDue(),
                'dueDate' => $next->due_date->toDateString(),
                'isDraft' => $next->sent_at === null,
                'isOverdue' => $next->due_date->lt($today),
            ],
            'thisMonth' => $this->month($today),
            'nextMonth' => $this->month($today->startOfMonth()->addMonth()),
        ];
    }

    /** @return array{month: string, total: string, collected: string, outstanding: string, invoices: int} */
    private function month(CarbonImmutable $day): array
    {
        $invoices = $this->invoices()
            ->dueWithin($day->startOfMonth(), $day->endOfMonth())
            ->get(['id', 'total', 'amount_paid']);

        $total = (float) $invoices->sum(fn (Invoice $invoice): float => (float) $invoice->total);
        $collected = (float) $invoices->sum(fn (Invoice $invoice): float => (float) $invoice->amount_paid);

        return [
            'month' => $day->format('Y-m'),
            'total' => number_format($total, 2, '.', ''),
            'collected' => number_format($collected, 2, '.', ''),
            'outstanding' => number_format(max(0, $total - $collected), 2, '.', ''),
            'invoices' => $invoices->count(),
        ];
    }
}
