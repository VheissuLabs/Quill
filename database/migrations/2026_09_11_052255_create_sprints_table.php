<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sprints');
    }
};
