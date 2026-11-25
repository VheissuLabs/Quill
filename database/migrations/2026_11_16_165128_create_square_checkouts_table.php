<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('square_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('payment_link_id');
            $table->string('order_id')->unique();
            $table->text('url');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('open');
            $table->string('square_payment_id')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('square_checkouts');
    }
};
