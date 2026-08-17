<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `organization_id` is the tenant root; `parent` is the structural parent and
     * may be an Organization or a Client. Both are nullable only because personal
     * teams still exist — PR 2c removes them and makes these required.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignUuid('organization_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('owner_id')->nullable()->after('slug')->constrained('users')->nullOnDelete();

            $table->nullableUuidMorphs('parent');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('owner_id');
            $table->dropMorphs('parent');
        });
    }
};
