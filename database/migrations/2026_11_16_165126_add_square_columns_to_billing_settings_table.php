<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->string('square_environment')->default('sandbox');
            $table->text('square_access_token')->nullable();
            $table->string('square_location_id')->nullable();
            $table->text('square_webhook_signature_key')->nullable();
            $table->string('square_webhook_token')->nullable()->unique();
            $table->text('payment_instructions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropUnique(['square_webhook_token']);
            $table->dropColumn([
                'square_environment',
                'square_access_token',
                'square_location_id',
                'square_webhook_signature_key',
                'square_webhook_token',
                'payment_instructions',
            ]);
        });
    }
};
