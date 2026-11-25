<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('status');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_columns');
    }
};
