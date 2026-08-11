<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record which client a contact represents.
     *
     * A contact is an ordinary member holding the `Client` role, so the client
     * they speak for belongs on the membership rather than on the user: the same
     * person may be a contact for one organization and staff at another.
     *
     * Nullable because it only applies to the `Client` role. That invariant is
     * not enforced here — `attach()` writes the pivot with a query rather than
     * through the model, so no model event fires. It is enforced wherever a
     * membership is created.
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
