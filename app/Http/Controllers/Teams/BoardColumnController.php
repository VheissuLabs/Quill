<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SaveBoardColumnRequest;
use App\Models\BoardColumn;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BoardColumnController extends Controller
{
    public function store(SaveBoardColumnRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        Gate::authorize('create', [BoardColumn::class, $team]);

        $team->columns()->create([
            ...$request->validated(),
            'position' => ($team->columns()->max('position') ?? -1) + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Column added.')]);

        return back();
    }

    public function update(SaveBoardColumnRequest $request, Organization $currentOrganization, Team $team, BoardColumn $column): RedirectResponse
    {
        Gate::authorize('update', $column);

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
        Gate::authorize('delete', $column);

        $columns = $team->columns()->get();

        abort_if($columns->count() === 1, 409, __('A board needs at least one column.'));

        $index = $columns->search(fn (BoardColumn $candidate) => $candidate->is($column));
        $fallback = $columns[$index - 1] ?? $columns[$index + 1];

        DB::transaction(function () use ($column, $fallback) {
            $column->issues()->each(fn (Issue $issue) => $issue->moveToColumn($fallback));

            $column->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Column removed. Its issues moved to :name.', ['name' => $fallback->name])]);

        return back();
    }
}
