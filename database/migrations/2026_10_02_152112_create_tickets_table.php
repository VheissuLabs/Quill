<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('subject');
            $table->string('status');
            $table->string('requester_name')->nullable();
            $table->string('requester_email');
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issue_resolved_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'last_message_at']);
            $table->index('requester_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
