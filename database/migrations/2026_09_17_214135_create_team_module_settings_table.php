<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_module_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->string('capability')->default('');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'module', 'capability'], 'team_module_settings_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_module_settings');
    }
};
