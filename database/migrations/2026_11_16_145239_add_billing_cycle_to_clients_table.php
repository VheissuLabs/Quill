<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('billing_cycle')->nullable();
            $table->date('billing_cycle_starts_on')->nullable();
            $table->json('billing_cc_emails')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->date('billing_opened_through')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'billing_cycle',
                'billing_cycle_starts_on',
                'billing_cc_emails',
                'payment_terms_days',
                'billing_opened_through',
            ]);
        });
    }
};
