<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Roles now live in Spatie's `model_has_roles`; two sources of truth is one too many. */
    public function up(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->string('role')->after('user_id');
        });
    }
};
