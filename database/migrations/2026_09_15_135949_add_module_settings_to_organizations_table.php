<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('feature_voting_enabled')->default(true);
            $table->boolean('issues_enabled')->default(true);
            $table->boolean('sprints_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'feature_voting_enabled',
                'issues_enabled',
                'sprints_enabled',
            ]);
        });
    }
};
