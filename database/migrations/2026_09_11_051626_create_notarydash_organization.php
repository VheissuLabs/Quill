<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        activity()->disableLogging();

        $karl = User::where('email', 'karl@vheissulabs.com')->firstOrFail();

        $karl->emailAddresses()->updateOrCreate(
            ['email' => 'karl@notarydash.com'],
            ['verified_at' => now()],
        );

        activity()->enableLogging();
    }

    public function down(): void
    {
        //
    }
};
