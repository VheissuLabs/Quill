<?php

namespace App\Http\Controllers\Sprints;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sprints\UpdateSprintColumnRequest;
use App\Models\BoardColumn;
use App\Models\Organization;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;

class SprintColumnController extends Controller
{
    public function update(UpdateSprintColumnRequest $request, Organization $currentOrganization, Sprint $sprint, BoardColumn $column): RedirectResponse
    {
        /** @var array<int, int> $issueIds */
        $issueIds = $request->validated('issue_ids');

        $sprint->arrangeColumn($column, $issueIds);

        return back();
    }
}
