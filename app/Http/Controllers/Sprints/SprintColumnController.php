<?php

namespace App\Http\Controllers\Sprints;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\UpdateSprintColumnRequest;
use App\Models\BoardColumn;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SprintColumnController extends Controller
{
    public function update(UpdateSprintColumnRequest $request, Organization $currentOrganization, Sprint $sprint, BoardColumn $column): RedirectResponse
    {
        Gate::authorize('view', $sprint);

        abort_unless($column->owner_type === $sprint->owner_type && $column->owner_id === $sprint->owner_id, 404);

        /** @var array<int, int> $issueIds */
        $issueIds = $request->validated('issue_ids');

        DB::transaction(function () use ($issueIds, $sprint, $column) {
            $issues = Issue::whereIn('id', $issueIds)->get()->keyBy('id');

            foreach (array_values($issueIds) as $position => $issueId) {
                $issue = $issues[$issueId];

                Gate::authorize('update', $issue);

                if ($issue->sprint_id !== $sprint->id) {
                    $issue->planInto($sprint);
                }

                $issue->moveToColumn($column);
                $issue->update(['position' => $position]);
            }
        });

        return back();
    }
}
