<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SplitInvoiceLinesByDay
{
    public function handle(Invoice $invoice): void
    {
        if (! $invoice->isEditable()) {
            return;
        }

        DB::transaction(function () use ($invoice): void {
            $invoice->lines()
                ->whereNull('completed_at')
                ->with('timeEntries')
                ->get()
                ->each(fn (InvoiceLine $line) => $this->split($line));
        });
    }

    private function split(InvoiceLine $line): void
    {
        $days = $line->timeEntries->groupBy(fn (TimeEntry $entry): string => $entry->completed_at->toDateString())->sortKeys();

        if ($days->isEmpty() || round((float) $line->quantity, 2) !== round((float) $line->timeEntries->sum('hours'), 2)) {
            return;
        }

        $days->each(function (Collection $entries, string $day) use ($line, $days): void {
            $target = $day === $days->keys()->first()
                ? $line
                : $line->replicate(['completed_at', 'quantity', 'amount']);

            $target->fill([
                'completed_at' => $day,
                'quantity' => round((float) $entries->sum('hours'), 2),
            ])->save();

            TimeEntry::query()->whereKey($entries->modelKeys())->update(['invoice_line_id' => $target->id]);
        });
    }
}
