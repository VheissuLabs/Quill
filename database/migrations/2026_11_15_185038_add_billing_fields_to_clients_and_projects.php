<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('billing_rate', 12, 2)->nullable();
            $table->foreignId('billing_contact_id')->nullable()->constrained('client_contacts')->nullOnDelete();
            $table->foreignId('billed_through_client_id')->nullable()->constrained('clients')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('billing_rate', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('billing_rate');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billed_through_client_id');
            $table->dropConstrainedForeignId('billing_contact_id');
            $table->dropColumn('billing_rate');
        });
    }
};
