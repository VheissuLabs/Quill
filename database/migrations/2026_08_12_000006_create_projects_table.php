<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A project is owned by a client (the whole account) or by one team.
     *
     * `owner` rather than `parent`, because a project has no role-based owner to
     * collide with — unlike teams and clients, where `owner()` already means the
     * user holding the Owner role.
     *
     * `organization_id` is carried for the same reason teams and clients carry it:
     * "every project in this organization" is a plain where instead of a walk up
     * an arbitrarily deep owner chain.
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
            /**
             * Nullable, despite the design calling it required. A project may be
             * owned by a client, so requiring the client to name a project at
             * creation is circular. Where the destination actually matters — an
             * issue must land somewhere — is where it gets enforced.
             */
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
