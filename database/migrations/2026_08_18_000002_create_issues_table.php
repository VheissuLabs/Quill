<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `client_id` is null for internal work: a project owned by a team serves
     * nobody outside the organization. A contact's issue always carries one,
     * which the observer enforces.
     *
     * `conversation_id` has no foreign key — the conversations table belongs to
     * laravel/ai and an issue must outlive a pruned transcript.
     */
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('issue_type_id')->constrained('issue_types')->restrictOnDelete();
            $table->foreignUuid('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('description');
            $table->text('acceptance_criteria')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'number']);
            $table->index(['organization_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
