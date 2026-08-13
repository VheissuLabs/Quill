<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * On the membership, not the user: the same person may be a contact for one
     * organization and staff at another. Nullable because it only applies to the
     * `Client` role, and that invariant is enforced on write — `attach()` writes the
     * pivot with a query, so no model event fires here.
     */
    public function up(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->foreignUuid('client_id')
                ->nullable()
                ->after('role')
                ->constrained('clients')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
