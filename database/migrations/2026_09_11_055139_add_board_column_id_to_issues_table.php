<?php

use App\Enums\IssueStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('board_column_id')->nullable()->after('sprint_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('points')->nullable()->after('priority');
        });

        DB::table('issues')->where('status', 'in_review')->update(['status' => IssueStatus::InProgress->value]);

        // Written against the schema of the day: board columns hung off a team_id, and a later
        // migration moves them to a polymorphic owner. Going through the models would read the
        // shape they have now, not the shape the table has here.
        $columnStatuses = array_map(fn (IssueStatus $status) => $status->value, IssueStatus::columnStatuses());

        DB::table('teams')
            ->whereNotIn('id', fn ($query) => $query->select('team_id')->from('board_columns'))
            ->pluck('id')
            ->each(function (int $teamId) use ($columnStatuses) {
                foreach ($columnStatuses as $position => $status) {
                    DB::table('board_columns')->insert([
                        'team_id' => $teamId,
                        'name' => IssueStatus::from($status)->label(),
                        'status' => $status,
                        'position' => $position,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        DB::table('issues')->whereNotNull('sprint_id')->orderBy('id')->each(function (object $issue) {
            $teamId = DB::table('sprints')->where('id', $issue->sprint_id)->value('team_id');

            $column = DB::table('board_columns')->where('team_id', $teamId)->where('status', $issue->status)->first()
                ?? DB::table('board_columns')->where('team_id', $teamId)->orderBy('position')->first();

            DB::table('issues')->where('id', $issue->id)->update(['board_column_id' => $column?->id]);
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_column_id');
            $table->dropColumn('points');
        });
    }
};
