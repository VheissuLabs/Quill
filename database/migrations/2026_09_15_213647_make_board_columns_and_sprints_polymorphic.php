<?php

use App\Models\Organization;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boards and sprints belonged to a team, which made teams mandatory for any organization that
 * wanted either. They now belong to whoever runs the board: a team, or the organization itself.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('board_columns', function (Blueprint $table) {
            $table->nullableMorphs('owner');
        });

        Schema::table('sprints', function (Blueprint $table) {
            $table->nullableMorphs('owner');
        });

        foreach (['board_columns', 'sprints'] as $table) {
            DB::table($table)->update([
                'owner_type' => (new Team)->getMorphClass(),
                'owner_id' => DB::raw('team_id'),
            ]);
        }

        // The foreign key leans on the composite index, so it has to go first.
        Schema::table('board_columns', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id', 'position']);
            $table->dropColumn('team_id');
            $table->index(['owner_type', 'owner_id', 'position']);
        });

        Schema::table('sprints', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id', 'status']);
            $table->dropColumn('team_id');
            $table->index(['owner_type', 'owner_id', 'status']);
        });

        // Organizations that never set up a team had no board at all. They get their own now.
        Organization::query()->doesntHave('teams')->each(
            fn (Organization $organization) => $organization->createDefaultColumns()
        );
    }

    /**
     * Reverse the migrations.
     *
     * Only team-owned boards can be restored; anything the organization had taken over has no
     * team to go back to and is dropped.
     */
    public function down(): void
    {
        Schema::table('board_columns', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('sprints', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
        });

        foreach (['board_columns', 'sprints'] as $table) {
            DB::table($table)->where('owner_type', (new Team)->getMorphClass())->update([
                'team_id' => DB::raw('owner_id'),
            ]);

            DB::table($table)->whereNull('team_id')->delete();
        }

        Schema::table('board_columns', function (Blueprint $table) {
            $table->dropIndex(['owner_type', 'owner_id', 'position']);
            $table->dropMorphs('owner');
            $table->index(['team_id', 'position']);
        });

        Schema::table('sprints', function (Blueprint $table) {
            $table->dropIndex(['owner_type', 'owner_id', 'status']);
            $table->dropMorphs('owner');
            $table->index(['team_id', 'status']);
        });
    }
};
