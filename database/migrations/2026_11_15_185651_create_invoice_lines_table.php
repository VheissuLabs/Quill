<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->default('hours');
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('work_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
