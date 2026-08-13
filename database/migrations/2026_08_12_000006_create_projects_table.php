<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `owner` rather than `parent`: unlike teams and clients, a project has no
     * role-based `owner()` for the name to collide with.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('owner', 'projects_owner_index');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'name']);
        });

        Schema::table('clients', function (Blueprint $table) {
            /** Nullable because a project may be owned by a client: requiring one at creation is circular. */
            $table->foreignUuid('default_project_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_project_id');
        });

        Schema::dropIfExists('projects');
    }
};
