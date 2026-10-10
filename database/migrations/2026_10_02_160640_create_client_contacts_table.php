<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email');
            $table->timestamps();

            $table->unique(['organization_id', 'email']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('client_contact_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_contact_id');
        });

        Schema::dropIfExists('client_contacts');
    }
};
