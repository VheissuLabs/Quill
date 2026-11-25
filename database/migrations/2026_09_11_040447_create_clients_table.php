<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->decimal('billing_rate', 12, 2)->nullable();
            $table->foreignId('billed_through_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('billing_cycle')->nullable();
            $table->date('billing_cycle_starts_on')->nullable();
            $table->date('billing_opened_through')->nullable();
            $table->json('billing_cc_emails')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
