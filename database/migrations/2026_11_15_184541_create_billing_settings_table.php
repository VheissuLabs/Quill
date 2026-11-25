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
            $table->text('from_address')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->string('square_environment')->default('sandbox');
            $table->text('square_access_token')->nullable();
            $table->string('square_location_id')->nullable();
            $table->text('square_webhook_signature_key')->nullable();
            $table->string('square_webhook_token')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
