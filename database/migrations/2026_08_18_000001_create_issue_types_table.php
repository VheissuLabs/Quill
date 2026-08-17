<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A null organization_id is a template every new organization is copied from,
     * the same shape the default roles use.
     */
    public function up(): void
    {
        Schema::create('issue_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_types');
    }
};
