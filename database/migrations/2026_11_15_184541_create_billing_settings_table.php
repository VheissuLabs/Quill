<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('invoice_prefix')->default('INV');
            $table->decimal('default_rate', 12, 2)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->text('default_notes')->nullable();
            $table->text('default_terms')->nullable();
            $table->text('from_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
