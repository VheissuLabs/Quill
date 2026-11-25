<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('visibility');
            $table->decimal('billing_rate', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('project_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_teams');
        Schema::dropIfExists('projects');
    }
};
