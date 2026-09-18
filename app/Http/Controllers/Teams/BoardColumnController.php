<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\BoardColumnRequest;
use App\Models\BoardColumn;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BoardColumnController extends Controller
{
    public function store(BoardColumnRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        $team->columns()->create([
            ...$request->validated(),
            'position' => ($team->columns()->max('position') ?? -1) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Column added.')]);

        return back();
    }

    public function update(BoardColumnRequest $request, Organization $currentOrganization, Team $team, BoardColumn $column): RedirectResponse
    {
        DB::transaction(function () use ($request, $column) {
            $column->update($request->validated());

            $column->issues()
                ->where('status', '!=', $column->status)
                ->each(fn (Issue $issue) => $issue->changeStatus($column->status));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Column updated.')]);

        return back();
    }

    public function destroy(Organization $currentOrganization, Team $team, BoardColumn $column): RedirectResponse
    {
        $fallback = $column->deleteAndReassignIssues();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Column removed. Its issues moved to :name.', ['name' => $fallback->name])]);

        return back();
    }
}
