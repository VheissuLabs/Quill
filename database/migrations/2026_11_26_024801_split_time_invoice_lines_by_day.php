<?php

use App\Actions\Billing\SplitInvoiceLinesByDay;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Invoice::query()
            ->whereNull('sent_at')
            ->where('status', '!=', InvoiceStatus::Void)
            ->whereHas('lines.timeEntries')
            ->each(fn (Invoice $invoice) => app(SplitInvoiceLinesByDay::class)->handle($invoice));
    }

    public function down(): void {}
};
