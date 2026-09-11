<?php

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Existing teams get the default columns and issues already in a sprint
     * are placed in the column matching their status.
     */
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('board_column_id')->nullable()->after('sprint_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('points')->nullable()->after('priority');
        });

        DB::table('issues')->where('status', 'in_review')->update(['status' => IssueStatus::InProgress->value]);

        Team::query()->doesntHave('columns')->each(fn (Team $team) => $team->createDefaultColumns());

        Issue::query()->whereNotNull('sprint_id')->with('sprint.team.columns')->each(function (Issue $issue) {
            $column = $issue->sprint->team->columns->firstWhere('status', $issue->status)
                ?? $issue->sprint->team->columns->first();

            $issue->update(['board_column_id' => $column?->id]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_column_id');
            $table->dropColumn('points');
        });
    }
};
