<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The published stub used `id()` and `nullableMorphs`, both of which are
     * integer keys. Quill's keys are UUIDs, and a UUID written to a bigint
     * truncates to 0 on MySQL — every subject and causer would collapse onto one
     * row. SQLite does not enforce types, so tests would not show it.
     *
     * `organization_id` is Quill's addition. Activity is only ever read scoped to
     * an organization, and Spatie has no notion of a tenant, so without it every
     * read would mean walking each subject's own tenancy.
     *
     * Deliberately not a foreign key. Deleting an organization is itself logged,
     * and that row is written after the organization is gone — a constraint would
     * reject the insert. A log that dies with the thing it describes cannot
     * record the deletion.
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableUuidMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableUuidMorphs('causer', 'causer');
            $table->uuid('organization_id')->nullable();
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
