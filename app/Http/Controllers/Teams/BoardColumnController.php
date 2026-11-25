<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\BoardColumnRequest;
use App\Models\BoardColumn;
use App\Models\Organization;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BoardColumnController extends Controller
{
    public function store(BoardColumnRequest $request, Organization $currentOrganization, Team $team): RedirectResponse
    {
        $team->addColumn($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Column added.')]);

        return back();
    }

    public function update(BoardColumnRequest $request, Organization $currentOrganization, Team $team, BoardColumn $column): RedirectResponse
    {
        $column->change($request->validated());

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
